<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Paddle;

use App\Exceptions\Paddle\SubscriptionException;
use App\Models\User;
use App\Services\Paddle\SubscriptionServiceFake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SubscriptionServiceFakeTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionServiceFake $fake;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paddle.monthly' => '123456']);
        config(['services.paddle.yearly' => '789012']);

        $this->fake = new SubscriptionServiceFake;
    }

    #[Test]
    public function it_prevents_duplicate_subscriptions(): void
    {
        $user = User::factory()->create();

        $this->fake->subscribe($user, 'monthly');

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('You already have an active subscription.');

        $this->fake->subscribe($user, 'yearly');
    }

    #[Test]
    public function it_throws_exception_for_invalid_plan_on_subscribe(): void
    {
        $user = User::factory()->create();

        config(['services.paddle.invalid_plan' => null]);

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('The invalid_plan plan is not configured. Please contact support.');

        $this->fake->subscribe($user, 'invalid_plan');
    }

    #[Test]
    public function it_throws_exception_for_invalid_plan_on_swap(): void
    {
        $user = User::factory()->create();
        $this->fake->setState($user, 'active');

        config(['services.paddle.invalid_plan' => null]);

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('The invalid_plan plan is not configured. Please contact support.');

        $this->fake->swap($user, 'invalid_plan');
    }

    #[Test]
    public function it_throws_exception_when_swapping_without_subscription(): void
    {
        $user = User::factory()->create();

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('You are not subscribed to a paid plan.');

        $this->fake->swap($user, 'yearly');
    }

    #[Test]
    public function it_is_idempotent_on_cancel(): void
    {
        $user = User::factory()->create();
        $this->fake->setState($user, 'active');

        $firstCancel = $this->fake->cancel($user, 'monthly');
        $secondCancel = $this->fake->cancel($user, 'monthly');

        $this->assertSame($firstCancel, $secondCancel);
    }

    #[Test]
    public function it_returns_success_when_canceling_non_existent_subscription(): void
    {
        $user = User::factory()->create();

        $result = $this->fake->cancel($user, 'monthly');

        $this->assertArrayHasKey('message', $result);
    }

    #[Test]
    public function it_simulates_different_subscription_states(): void
    {
        $user = User::factory()->create();

        $this->fake->setState($user, 'trialing');
        $this->fake->setState($user, 'past_due');
        $this->fake->setState($user, 'paused');
        $this->fake->setState($user, 'canceled');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_includes_context_in_duplicate_subscription_exception(): void
    {
        $user = User::factory()->create();

        $this->fake->subscribe($user, 'monthly');

        try {
            $this->fake->subscribe($user, 'yearly');
        } catch (SubscriptionException $e) {
            $context = $e->context();

            $this->assertArrayHasKey('action', $context);
            $this->assertSame('subscribe', $context['action']);
            $this->assertArrayHasKey('plan', $context);
            $this->assertSame('yearly', $context['plan']);
            $this->assertArrayHasKey('current_state', $context);
            $this->assertSame('active', $context['current_state']);
        }
    }

    #[Test]
    public function it_clears_subscriptions(): void
    {
        $user = User::factory()->create();

        $this->fake->subscribe($user, 'monthly');
        $this->fake->clearSubscriptions();

        $this->fake->subscribe($user, 'yearly');

        $this->expectNotToPerformAssertions();
    }
}
