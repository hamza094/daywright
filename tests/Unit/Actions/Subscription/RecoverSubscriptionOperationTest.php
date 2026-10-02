<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Subscription;

use App\Actions\Subscription\RecoverSubscriptionOperation;
use App\Actions\Subscription\VerifySubscriptionOperation;
use App\DataTransferObjects\Paddle\PaddleSubscriptionSnapshot;
use App\Enums\Subscription\SubscriptionOperationRecoveryOutcome;
use App\Enums\Subscription\SubscriptionOperationStatus;
use App\Interfaces\Paddle\CashierGatewayInterface;
use App\Models\SubscriptionOperation;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\SubscriptionHelpers;
use Tests\TestCase;

final class RecoverSubscriptionOperationTest extends TestCase
{
    use RefreshDatabase;
    use SubscriptionHelpers;

    private CashierGatewayInterface&MockInterface $cashier;

    private RecoverSubscriptionOperation $action;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paddle.monthly' => 123]);
        config(['services.paddle.yearly' => 456]);
        config(['services.paddle.subscription_name' => 'daywright']);

        $this->cashier = Mockery::mock(CashierGatewayInterface::class);
        $this->action = new RecoverSubscriptionOperation($this->cashier, new VerifySubscriptionOperation);
    }

    #[Test]
    public function it_recovers_matching_swap_operation(): void
    {
        $user = User::factory()->create();
        $sub = $this->createProSubscription($user, ['paddle_id' => 998877, 'paddle_plan' => 123]);

        $operation = SubscriptionOperation::factory()->swap('yearly')->unknown()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'paddle_subscription_id' => '998877',
            'attempts' => 1,
        ]);

        $this->cashier->shouldReceive('getSubscription')
            ->once()
            ->with('998877')
            ->andReturn(new PaddleSubscriptionSnapshot(
                subscription_id: '998877',
                plan_id: '456', // The actual plan ID from config, not the key
                status: 'active',
            ));

        $outcome = $this->action->execute($operation);

        $this->assertSame(SubscriptionOperationRecoveryOutcome::Recovered, $outcome);

        $operation->refresh();
        $this->assertSame(SubscriptionOperationStatus::Completed, $operation->status);
        $this->assertNotNull($operation->completed_at);
        $this->assertSame(2, $operation->attempts);

        $sub->refresh();
        $this->assertSame(456, $sub->paddle_plan);
    }

    #[Test]
    public function it_recovers_matching_cancel_operation(): void
    {
        $user = User::factory()->create();
        $sub = $this->createProSubscription($user, ['paddle_id' => 998877, 'paddle_status' => 'active']);

        $operation = SubscriptionOperation::factory()->cancel()->unknown()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'paddle_subscription_id' => '998877',
            'attempts' => 1,
        ]);

        $this->cashier->shouldReceive('getSubscription')
            ->once()
            ->with('998877')
            ->andReturn(new PaddleSubscriptionSnapshot(
                subscription_id: '998877',
                plan_id: '123',
                status: 'deleted',
                cancellation_effective_date: now()->toIso8601String(),
            ));

        $outcome = $this->action->execute($operation);

        $this->assertSame(SubscriptionOperationRecoveryOutcome::Recovered, $outcome);

        $operation->refresh();
        $this->assertSame(SubscriptionOperationStatus::Completed, $operation->status);

        $sub->refresh();
        $this->assertSame('deleted', $sub->paddle_status); // Cashier-compatible cancellation status
    }

    #[Test]
    public function it_does_not_reschedule_after_another_worker_takes_the_claim(): void
    {
        $user = User::factory()->create();
        $operation = SubscriptionOperation::factory()->swap('yearly')->unknown()->create([
            'user_id' => $user->id,
            'paddle_subscription_id' => '998877',
            'attempts' => 0,
        ]);

        $this->cashier->shouldReceive('getSubscription')
            ->once()
            ->andReturnUsing(function () use ($operation): null {
                SubscriptionOperation::query()->whereKey($operation->id)->update([
                    'claim_token' => (string) Str::uuid(),
                    'claim_expires_at' => now()->addMinutes(5),
                ]);

                return null;
            });

        $outcome = $this->action->execute($operation);

        $this->assertSame(SubscriptionOperationRecoveryOutcome::Skipped, $outcome);
        $this->assertSame(SubscriptionOperationStatus::Processing, $operation->fresh()->status);
        $this->assertSame(0, $operation->fresh()->attempts);
    }

    #[Test]
    public function it_retries_and_sets_backoff_delay_on_mismatch(): void
    {
        $user = User::factory()->create();
        $sub = $this->createProSubscription($user, ['paddle_id' => 998877, 'paddle_plan' => 123]);

        $operation = SubscriptionOperation::factory()->swap('yearly')->unknown()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'paddle_subscription_id' => '998877',
            'attempts' => 1,
        ]);

        // Remote plan is still monthly (123), not target yearly (456)
        $this->cashier->shouldReceive('getSubscription')
            ->once()
            ->with('998877')
            ->andReturn(new PaddleSubscriptionSnapshot(
                subscription_id: '998877',
                plan_id: '123',
                status: 'active',
            ));

        $outcome = $this->action->execute($operation);

        $this->assertSame(SubscriptionOperationRecoveryOutcome::Unresolved, $outcome);

        $operation->refresh();
        $this->assertSame(SubscriptionOperationStatus::Unknown, $operation->status);
        $this->assertSame(2, $operation->attempts);
        $this->assertNotNull($operation->available_at);
        $this->assertTrue($operation->available_at->isFuture());
    }

    #[Test]
    public function it_moves_to_manual_review_after_five_unsuccessful_reads(): void
    {
        $user = User::factory()->create();
        $sub = $this->createProSubscription($user, ['paddle_id' => 998877, 'paddle_plan' => 123]);

        $operation = SubscriptionOperation::factory()->swap('yearly')->unknown()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'paddle_subscription_id' => '998877',
            'attempts' => 4,
        ]);

        $this->cashier->shouldReceive('getSubscription')
            ->once()
            ->with('998877')
            ->andReturn(new PaddleSubscriptionSnapshot(
                subscription_id: '998877',
                plan_id: '123',
                status: 'active',
            ));

        $outcome = $this->action->execute($operation);

        $this->assertSame(SubscriptionOperationRecoveryOutcome::ManualReview, $outcome);

        $operation->refresh();
        $this->assertSame(SubscriptionOperationStatus::ManualReview, $operation->status);
        $this->assertSame(5, $operation->attempts);
    }

    #[Test]
    public function it_handles_read_exception_gracefully_and_retries(): void
    {
        $user = User::factory()->create();

        $operation = SubscriptionOperation::factory()->swap('yearly')->unknown()->create([
            'user_id' => $user->id,
            'paddle_subscription_id' => '998877',
            'attempts' => 0,
        ]);

        $this->cashier->shouldReceive('getSubscription')
            ->once()
            ->with('998877')
            ->andThrow(new Exception('Network error'));

        $outcome = $this->action->execute($operation);

        $this->assertSame(SubscriptionOperationRecoveryOutcome::Unresolved, $outcome);

        $operation->refresh();
        $this->assertSame(SubscriptionOperationStatus::Unknown, $operation->status);
        $this->assertSame(1, $operation->attempts);
    }

    #[Test]
    public function it_skips_operation_if_already_claimed_or_completed(): void
    {
        $user = User::factory()->create();

        $operation = SubscriptionOperation::factory()->swap('yearly')->completed()->create([
            'user_id' => $user->id,
            'paddle_subscription_id' => '998877',
        ]);

        $this->cashier->shouldNotReceive('getSubscription');

        $outcome = $this->action->execute($operation);

        $this->assertSame(SubscriptionOperationRecoveryOutcome::Skipped, $outcome);
    }

    #[Test]
    public function it_skips_an_unknown_operation_until_its_backoff_is_due(): void
    {
        $user = User::factory()->create();
        $operation = SubscriptionOperation::factory()->swap('yearly')->unknown()->create([
            'user_id' => $user->id,
            'paddle_subscription_id' => '998877',
            'available_at' => now()->addMinute(),
        ]);

        $this->cashier->shouldNotReceive('getSubscription');

        $outcome = $this->action->execute($operation);

        $this->assertSame(SubscriptionOperationRecoveryOutcome::Skipped, $outcome);
        $this->assertSame(SubscriptionOperationStatus::Unknown, $operation->fresh()->status);
    }

    #[Test]
    public function it_does_not_complete_when_the_local_subscription_is_missing(): void
    {
        $user = User::factory()->create();
        $operation = SubscriptionOperation::factory()->swap('yearly')->unknown()->create([
            'user_id' => $user->id,
            'paddle_subscription_id' => '998877',
        ]);

        $this->cashier->shouldReceive('getSubscription')
            ->once()
            ->andReturn(new PaddleSubscriptionSnapshot('998877', '456', 'active'));

        $this->assertSame(SubscriptionOperationRecoveryOutcome::ManualReview, $this->action->execute($operation));
        $this->assertSame(SubscriptionOperationStatus::ManualReview, $operation->fresh()->status);
    }
}
