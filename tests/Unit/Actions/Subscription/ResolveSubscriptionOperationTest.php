<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Subscription;

use App\Actions\Subscription\ResolveSubscriptionOperation;
use App\Actions\Subscription\VerifySubscriptionOperation;
use App\DataTransferObjects\Paddle\PaddleSubscriptionSnapshot;
use App\Enums\Subscription\SubscriptionOperationStatus;
use App\Interfaces\Paddle\CashierGatewayInterface;
use App\Models\SubscriptionOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Laravel\Paddle\Subscription;
use LogicException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\SubscriptionHelpers;
use Tests\TestCase;

final class ResolveSubscriptionOperationTest extends TestCase
{
    use RefreshDatabase;
    use SubscriptionHelpers;

    private CashierGatewayInterface&MockInterface $cashier;

    private ResolveSubscriptionOperation $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paddle.monthly' => 123]);
        config(['services.paddle.yearly' => 456]);
        config(['services.paddle.subscription_name' => 'daywright']);

        $this->cashier = Mockery::mock(CashierGatewayInterface::class);
        $this->resolver = new ResolveSubscriptionOperation($this->cashier, new VerifySubscriptionOperation);
    }

    #[Test]
    public function it_rejects_mark_completed_for_non_manual_review_status(): void
    {
        $user = User::factory()->create();

        $operation = SubscriptionOperation::factory()->swap('yearly')->unknown()->create([
            'user_id' => $user->id,
            'paddle_subscription_id' => '998877',
        ]);

        $this->cashier->shouldNotReceive('getSubscription');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('is not in manual_review state');

        $this->resolver->markCompleted($operation);
    }

    #[Test]
    public function it_authoritatively_marks_completed_for_verified_swap(): void
    {
        $user = User::factory()->create();
        $sub = $this->createProSubscription($user, ['paddle_id' => '998877', 'paddle_plan' => 123]);

        $operation = SubscriptionOperation::factory()->swap('yearly')->manualReview()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'paddle_subscription_id' => '998877',
        ]);

        $this->cashier->shouldReceive('getSubscription')
            ->once()
            ->with('998877')
            ->andReturn(new PaddleSubscriptionSnapshot(
                subscription_id: '998877',
                plan_id: '456', // The actual plan ID from config, not the key
                status: 'active',
            ));

        $resolved = $this->resolver->markCompleted($operation);

        $this->assertSame(SubscriptionOperationStatus::Completed, $resolved->status);
        $this->assertNotNull($resolved->completed_at);

        $sub->refresh();
        $this->assertSame(456, $sub->paddle_plan);
    }

    #[Test]
    public function it_authoritatively_marks_completed_for_verified_cancel(): void
    {
        $user = User::factory()->create();
        $sub = $this->createProSubscription($user, ['paddle_id' => 998877, 'paddle_status' => 'active']);

        $operation = SubscriptionOperation::factory()->cancel()->manualReview()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'paddle_subscription_id' => '998877',
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

        $resolved = $this->resolver->markCompleted($operation);

        $this->assertSame(SubscriptionOperationStatus::Completed, $resolved->status);

        $sub->refresh();
        $this->assertSame('deleted', $sub->paddle_status); // Cashier-compatible cancellation status
    }

    #[Test]
    public function it_rejects_mark_completed_if_remote_plan_does_not_match(): void
    {
        $user = User::factory()->create();

        $operation = SubscriptionOperation::factory()->swap('yearly')->manualReview()->create([
            'user_id' => $user->id,
            'paddle_subscription_id' => '998877',
        ]);

        $this->cashier->shouldReceive('getSubscription')
            ->once()
            ->with('998877')
            ->andReturn(new PaddleSubscriptionSnapshot(
                subscription_id: '998877',
                plan_id: '123', // Still monthly!
                status: 'active',
            ));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot mark completed: Remote snapshot does not prove swap succeeded');

        $this->resolver->markCompleted($operation);
    }

    #[Test]
    public function it_rejects_mark_completed_if_remote_subscription_not_canceled(): void
    {
        $user = User::factory()->create();

        $operation = SubscriptionOperation::factory()->cancel()->manualReview()->create([
            'user_id' => $user->id,
            'paddle_subscription_id' => '998877',
        ]);

        $this->cashier->shouldReceive('getSubscription')
            ->once()
            ->with('998877')
            ->andReturn(new PaddleSubscriptionSnapshot(
                subscription_id: '998877',
                plan_id: '123',
                status: 'active',
            ));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot mark completed: Remote snapshot does not prove cancel succeeded');

        $this->resolver->markCompleted($operation);
    }

    #[Test]
    public function it_marks_operation_failed_with_incident_reference(): void
    {
        $user = User::factory()->create();

        $operation = SubscriptionOperation::factory()->swap('yearly')->manualReview()->create([
            'user_id' => $user->id,
            'paddle_subscription_id' => '998877',
        ]);

        $this->cashier->shouldNotReceive('getSubscription');

        $resolved = $this->resolver->markFailed($operation, 'INC-404-PADDLE');

        $this->assertSame(SubscriptionOperationStatus::Failed, $resolved->status);
        $this->assertSame('INC-404-PADDLE', $resolved->last_error_code);
        $this->assertSame('ManualResolution', $resolved->last_error_class);
        $this->assertNotNull($resolved->failed_at);
    }

    #[Test]
    public function it_requires_non_empty_reference_when_marking_failed(): void
    {
        $user = User::factory()->create();

        $operation = SubscriptionOperation::factory()->swap('yearly')->manualReview()->create([
            'user_id' => $user->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A ticket or incident reference is required');

        $this->resolver->markFailed($operation, '   ');
    }

    #[Test]
    public function it_rejects_resolution_on_already_terminal_operation(): void
    {
        $user = User::factory()->create();

        $operation = SubscriptionOperation::factory()->swap('yearly')->completed()->create([
            'user_id' => $user->id,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('is not in manual_review state');

        $this->resolver->markCompleted($operation);
    }

    #[Test]
    public function it_requires_matching_paddle_id_for_manual_resolution(): void
    {
        $user = User::factory()->create();

        // Original subscription with Paddle ID 998877
        $originalSub = $this->createProSubscription($user, ['paddle_id' => 998877, 'paddle_plan' => 123]);

        // Operation for the original subscription
        $operation = SubscriptionOperation::factory()->swap('yearly')->manualReview()->create([
            'user_id' => $user->id,
            'subscription_id' => $originalSub->id,
            'paddle_subscription_id' => '998877',
        ]);

        $this->cashier->shouldReceive('getSubscription')
            ->once()
            ->with('998877')
            ->andReturn(new PaddleSubscriptionSnapshot(
                subscription_id: '998877',
                plan_id: '456',
                status: 'active',
            ));

        $resolved = $this->resolver->markCompleted($operation);

        $this->assertSame(SubscriptionOperationStatus::Completed, $resolved->status);

        // Original subscription should be updated
        $originalSub->refresh();
        $this->assertSame(456, $originalSub->paddle_plan);
    }

    #[Test]
    public function it_fails_when_subscription_with_matching_paddle_id_not_found(): void
    {
        $user = User::factory()->create();

        // Operation for a subscription that no longer exists locally
        $operation = SubscriptionOperation::factory()->swap('yearly')->manualReview()->create([
            'user_id' => $user->id,
            'subscription_id' => null,
            'paddle_subscription_id' => '998877',
        ]);

        $this->cashier->shouldReceive('getSubscription')
            ->once()
            ->with('998877')
            ->andReturn(new PaddleSubscriptionSnapshot(
                subscription_id: '998877',
                plan_id: '456',
                status: 'active',
            ));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot mark completed: local subscription with matching Paddle ID not found');

        $this->resolver->markCompleted($operation);
    }
}
