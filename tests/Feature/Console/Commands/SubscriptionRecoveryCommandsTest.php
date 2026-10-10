<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\DataTransferObjects\Paddle\PaddleSubscriptionSnapshot;
use App\Enums\Subscription\SubscriptionOperationStatus;
use App\Interfaces\Paddle\CashierGatewayInterface;
use App\Models\SubscriptionOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\SubscriptionHelpers;
use Tests\TestCase;

final class SubscriptionRecoveryCommandsTest extends TestCase
{
    use RefreshDatabase;
    use SubscriptionHelpers;

    private CashierGatewayInterface&MockInterface $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paddle.monthly' => 123]);
        config(['services.paddle.yearly' => 456]);
        config(['services.paddle.subscription_name' => 'daywright']);

        $this->cashier = Mockery::mock(CashierGatewayInterface::class);
        $this->app->instance(CashierGatewayInterface::class, $this->cashier);
    }

    #[Test]
    public function it_runs_recover_operations_command_successfully(): void
    {
        $user = User::factory()->create();
        $subscription = $this->createProSubscription($user, ['paddle_id' => 998877, 'paddle_plan' => 123]);

        $op1 = SubscriptionOperation::factory()->swap('yearly')->unknown()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'paddle_subscription_id' => '998877',
            'available_at' => now()->subMinute(),
        ]);

        $this->cashier->shouldReceive('getSubscription')
            ->once()
            ->with('998877')
            ->andReturn(new PaddleSubscriptionSnapshot('998877', '456', 'active'));

        $this->artisan('subscriptions:recover-operations', ['--limit' => 10])
            ->expectsOutputToContain('Selected: 1')
            ->expectsOutputToContain('Recovered: 1')
            ->expectsOutputToContain('Subscription operations recovery completed successfully.')
            ->assertExitCode(0);

        $op1->refresh();
        $this->assertSame(SubscriptionOperationStatus::Completed, $op1->status);
    }

    #[Test]
    public function it_resolves_operation_with_mark_completed(): void
    {
        $user = User::factory()->create();
        $subscription = $this->createProSubscription($user, ['paddle_id' => 998877, 'paddle_plan' => 123]);

        $operation = SubscriptionOperation::factory()->swap('yearly')->manualReview()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'paddle_subscription_id' => '998877',
        ]);

        $this->cashier->shouldReceive('getSubscription')
            ->once()
            ->with('998877')
            ->andReturn(new PaddleSubscriptionSnapshot('998877', '456', 'active'));

        $this->artisan('subscriptions:resolve-operation', [
            'operation_uuid' => $operation->operation_uuid,
            '--mark-completed' => true,
        ])
            ->expectsOutputToContain('verified with Paddle and marked completed')
            ->assertExitCode(0);

        $operation->refresh();
        $this->assertSame(SubscriptionOperationStatus::Completed, $operation->status);
    }

    #[Test]
    public function it_resolves_operation_with_mark_failed(): void
    {
        $user = User::factory()->create();

        $operation = SubscriptionOperation::factory()->swap('yearly')->manualReview()->create([
            'user_id' => $user->id,
            'paddle_subscription_id' => '998877',
        ]);

        $this->cashier->shouldNotReceive('getSubscription');

        $this->artisan('subscriptions:resolve-operation', [
            'operation_uuid' => $operation->operation_uuid,
            '--mark-failed' => true,
            '--reference' => 'INC-789',
        ])
            ->expectsOutputToContain('marked failed with reference [INC-789]')
            ->assertExitCode(0);

        $operation->refresh();
        $this->assertSame(SubscriptionOperationStatus::Failed, $operation->status);
        $this->assertSame('INC-789', $operation->last_error_code);
    }

    #[Test]
    public function it_rejects_command_when_both_or_neither_flags_are_passed(): void
    {
        $user = User::factory()->create();

        $operation = SubscriptionOperation::factory()->swap('yearly')->manualReview()->create([
            'user_id' => $user->id,
        ]);

        $this->artisan('subscriptions:resolve-operation', [
            'operation_uuid' => $operation->operation_uuid,
        ])
            ->expectsOutputToContain('Provide exactly one of --mark-completed or --mark-failed.')
            ->assertExitCode(1);

        $this->artisan('subscriptions:resolve-operation', [
            'operation_uuid' => $operation->operation_uuid,
            '--mark-completed' => true,
            '--mark-failed' => true,
            '--reference' => 'INC-123',
        ])
            ->expectsOutputToContain('Provide exactly one of --mark-completed or --mark-failed.')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_requires_reference_flag_when_marking_failed(): void
    {
        $user = User::factory()->create();

        $operation = SubscriptionOperation::factory()->swap('yearly')->manualReview()->create([
            'user_id' => $user->id,
        ]);

        $this->artisan('subscriptions:resolve-operation', [
            'operation_uuid' => $operation->operation_uuid,
            '--mark-failed' => true,
        ])
            ->expectsOutputToContain('A ticket or incident reference is required')
            ->assertExitCode(1);
    }
}
