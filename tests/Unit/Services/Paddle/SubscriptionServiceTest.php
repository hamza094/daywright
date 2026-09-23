<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Paddle;

use App\Enums\Subscription\SubscriptionOperationStatus;
use App\Enums\Subscription\SubscriptionOperationType;
use App\Exceptions\Paddle\ActiveOperationConflictException;
use App\Exceptions\Paddle\IdempotencyMismatchException;
use App\Exceptions\Paddle\PaddleUnavailableException;
use App\Exceptions\Paddle\SubscriptionException;
use App\Interfaces\Paddle\CashierGatewayInterface;
use App\Models\SubscriptionOperation;
use App\Models\User;
use App\Services\Paddle\SubscriptionService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Paddle\Exceptions\PaddleException;
use Laravel\Paddle\Subscription as PaddleSubscription;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\SubscriptionHelpers;
use Tests\TestCase;

final class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;
    use SubscriptionHelpers;

    private CashierGatewayInterface&MockInterface $cashier;

    private SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paddle.monthly' => 123]);
        config(['services.paddle.yearly' => 456]);
        config(['services.paddle.subscription_name' => 'daywright']);
        config(['app.url' => 'https://daywright.test']);

        $this->cashier = Mockery::mock(CashierGatewayInterface::class);
        $this->service = new SubscriptionService($this->cashier);
    }

    #[Test]
    public function it_generates_paylink_for_new_subscription(): void
    {
        $user = User::factory()->create();

        $this->cashier->shouldReceive('generatePayLink')
            ->once()
            ->with(Mockery::on(fn (User $u) => $u->id === $user->id), 123, 'https://daywright.test/subscriptions')
            ->andReturn('https://checkout.paddle.com/pay/12345');

        $url = $this->service->subscribe($user, 'monthly');

        $this->assertSame('https://checkout.paddle.com/pay/12345', $url);
    }

    #[Test]
    public function it_throws_exception_for_already_subscribed_user_on_subscribe(): void
    {
        /** @var User&MockInterface $user */
        $user = Mockery::mock(User::class);
        $user->shouldReceive('subscriptionName')->andReturn('daywright');
        $user->shouldReceive('subscription')->with('daywright')->andReturn(null);
        $user->shouldReceive('isSubscribed')->andReturn(true);
        $user->shouldReceive('isBillingSubscribed')->andReturn(true);
        $user->shouldReceive('activeBillingPlan')->andReturn('monthly');
        $user->shouldReceive('loadMissing')->andReturnSelf();

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('You are already subscribed to this plan.');

        $this->service->subscribe($user, 'monthly');
    }

    #[Test]
    public function it_throws_error_while_swapping_to_the_same_plan(): void
    {
        $user = User::factory()->create();

        /** @var User&MockInterface $userMock */
        $userMock = Mockery::mock($user);
        $userMock->shouldReceive('subscriptionName')->andReturn('daywright');
        $userMock->shouldReceive('subscription')->with('daywright')->andReturn(null);
        $userMock->shouldReceive('isBillingSubscribed')->andReturn(true);
        $userMock->shouldReceive('activeBillingPlan')->andReturn('yearly');
        $userMock->shouldReceive('loadMissing')->andReturnSelf();

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('You are already on this plan.');

        $this->service->swap($userMock, 'yearly', (string) Str::uuid());
    }

    #[Test]
    public function it_throws_exception_for_swapping_without_a_valid_subscription(): void
    {
        $user = User::factory()->create();

        /** @var User&MockInterface $userMock */
        $userMock = Mockery::mock($user);
        $userMock->shouldReceive('subscriptionName')->andReturn('daywright');
        $userMock->shouldReceive('subscription')->with('daywright')->andReturn(null);
        $userMock->shouldReceive('isBillingSubscribed')->andReturn(false);
        $userMock->shouldReceive('loadMissing')->andReturnSelf();

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('You are not subscribed to a paid plan.');

        $this->service->swap($userMock, 'yearly', (string) Str::uuid());
    }

    #[Test]
    public function it_treats_repeat_cancel_as_a_safe_no_op(): void
    {
        $user = User::factory()->create();

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('You are not subscribed to a paid plan.');

        $this->service->cancel($user, 'yearly', (string) Str::uuid());
    }

    #[Test]
    public function it_throws_exception_for_canceling_a_different_plan(): void
    {
        $user = User::factory()->create();

        /** @var User&MockInterface $userMock */
        $userMock = Mockery::mock($user);
        $userMock->shouldReceive('getKey')->andReturn($user->id);
        $userMock->shouldReceive('subscriptionName')->andReturn('daywright');
        $userMock->shouldReceive('subscription')->with('daywright')->andReturn(null);
        $userMock->shouldReceive('isBillingSubscribed')->andReturn(true);
        $userMock->shouldReceive('activeBillingPlan')->andReturn('monthly');
        $userMock->shouldReceive('loadMissing')->andReturnSelf();

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('You are not subscribed to this plan.');

        $this->service->cancel($userMock, 'yearly', (string) Str::uuid());
    }

    #[Test]
    public function it_successfully_swaps_plan_and_records_completed_operation(): void
    {
        $user = User::factory()->create();
        $this->createProSubscription($user, ['name' => 'daywright', 'paddle_id' => 998877]);

        $this->cashier->shouldReceive('swapAndInvoice')
            ->once()
            ->with(Mockery::type(PaddleSubscription::class), 456);

        $idempotencyKey = (string) Str::uuid();
        $result = $this->service->swap($user, 'yearly', $idempotencyKey);

        $this->assertSame(SubscriptionOperationStatus::Completed, $result->operation->status);
        $this->assertNotNull($result->operation->provider_attempted_at);
        $this->assertNotNull($result->operation->completed_at);
        $this->assertSame('998877', $result->operation->paddle_subscription_id);
        $this->assertSame('yearly', $result->operation->target_plan);
        $this->assertSame(SubscriptionOperationType::Swap, $result->operation->type);
    }

    #[Test]
    public function it_handles_paddle_exception_on_swap_and_records_failed_operation(): void
    {
        $user = User::factory()->create();
        $this->createProSubscription($user, ['name' => 'daywright', 'paddle_id' => 998877]);

        $this->cashier->shouldReceive('swapAndInvoice')
            ->once()
            ->andThrow(new PaddleException('Payment declined', 101));

        $idempotencyKey = (string) Str::uuid();

        $this->expectException(PaddleUnavailableException::class);

        try {
            $this->service->swap($user, 'yearly', $idempotencyKey);
        } finally {
            $operation = SubscriptionOperation::query()->where('user_id', $user->id)->first();
            $this->assertNotNull($operation);
            $this->assertSame(SubscriptionOperationStatus::Failed, $operation->status);
            $this->assertNotNull($operation->failed_at);
            $this->assertSame(PaddleException::class, $operation->last_error_class);
        }
    }

    #[Test]
    public function it_handles_unknown_exception_on_swap_and_records_unknown_operation(): void
    {
        $user = User::factory()->create();
        $this->createProSubscription($user, ['name' => 'daywright', 'paddle_id' => 998877]);

        $this->cashier->shouldReceive('swapAndInvoice')
            ->once()
            ->andThrow(new Exception('Network timeout'));

        $idempotencyKey = (string) Str::uuid();
        $result = $this->service->swap($user, 'yearly', $idempotencyKey);

        $this->assertSame(SubscriptionOperationStatus::Unknown, $result->operation->status);
        $this->assertNotNull($result->operation->available_at);
    }

    #[Test]
    public function it_successfully_cancels_plan_and_records_completed_operation(): void
    {
        $user = User::factory()->create();
        $this->createProSubscription($user, ['name' => 'daywright', 'paddle_id' => 998877, 'paddle_plan' => 123]);

        $this->cashier->shouldReceive('cancel')
            ->once()
            ->with(Mockery::type(PaddleSubscription::class));

        $idempotencyKey = (string) Str::uuid();
        $result = $this->service->cancel($user, 'monthly', $idempotencyKey);

        $this->assertSame(SubscriptionOperationStatus::Completed, $result->operation->status);
        $this->assertNotNull($result->operation->completed_at);
        $this->assertSame(SubscriptionOperationType::Cancel, $result->operation->type);
    }

    #[Test]
    public function it_returns_existing_completed_operation_for_idempotent_swap(): void
    {
        $user = User::factory()->create();
        $this->createProSubscription($user, ['name' => 'daywright', 'paddle_id' => 998877]);

        $idempotencyKey = (string) Str::uuid();

        $this->cashier->shouldReceive('swapAndInvoice')->once();

        $result1 = $this->service->swap($user, 'yearly', $idempotencyKey);
        $result1->operation->refresh();
        $this->assertSame(SubscriptionOperationStatus::Completed, $result1->operation->status);

        // Second call with same idempotency key - should not call Paddle again
        $result2 = $this->service->swap($user, 'yearly', $idempotencyKey);
        $result2->operation->refresh();
        $this->assertSame(SubscriptionOperationStatus::Completed, $result2->operation->status);
        $this->assertSame($result1->operation->id, $result2->operation->id);
    }

    #[Test]
    public function it_does_not_call_paddle_again_for_an_operation_still_processing(): void
    {
        $user = User::factory()->create();
        $this->createProSubscription($user, ['name' => 'daywright', 'paddle_id' => 998877]);
        $key = (string) Str::uuid();

        $operation = SubscriptionOperation::factory()->swap('yearly')->processing()->create([
            'user_id' => $user->id,
            'idempotency_key_hash' => hash('sha256', $key),
            'request_fingerprint' => hash('sha256', "{$user->id}:swap:yearly"),
        ]);

        $this->cashier->shouldNotReceive('swapAndInvoice');

        $result = $this->service->swap($user, 'yearly', $key);

        $this->assertSame($operation->id, $result->operation->id);
        $this->assertSame(SubscriptionOperationStatus::Processing, $result->operation->status);
    }

    #[Test]
    public function it_rejects_reusing_a_key_for_a_different_plan(): void
    {
        $user = User::factory()->create();
        $this->createProSubscription($user, ['name' => 'daywright', 'paddle_id' => 998877]);
        $key = (string) Str::uuid();

        SubscriptionOperation::factory()->swap('yearly')->processing()->create([
            'user_id' => $user->id,
            'idempotency_key_hash' => hash('sha256', $key),
            'request_fingerprint' => hash('sha256', "{$user->id}:swap:yearly"),
        ]);

        $this->cashier->shouldNotReceive('swapAndInvoice');
        $this->expectException(IdempotencyMismatchException::class);

        $this->service->swap($user, 'monthly', $key);
    }

    #[Test]
    public function it_throws_conflict_exception_when_active_operation_exists(): void
    {
        $user = User::factory()->create();
        $this->createProSubscription($user, ['name' => 'daywright', 'paddle_id' => 998877]);

        SubscriptionOperation::factory()->swap('yearly')->processing()->create([
            'user_id' => $user->id,
            'idempotency_key_hash' => hash('sha256', (string) Str::uuid()),
            'request_fingerprint' => hash('sha256', "{$user->id}:swap:yearly"),
        ]);

        $this->expectException(ActiveOperationConflictException::class);

        $this->service->swap($user, 'yearly', (string) Str::uuid());
    }
}
