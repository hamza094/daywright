<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Services\Paddle;

use App\Exceptions\Paddle\SubscriptionException;
use App\Models\User;
use App\Services\Api\V1\Paddle\SubscriptionService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    #[Test]
    public function it_throws_exception_for_already_subscribed_user(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isBillingSubscribed')->andReturn(true);
        $user->shouldReceive('billingPlan')->andReturn('monthly');

        $service = new SubscriptionService;

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('You are already subscribed to this plan.');

        $service->subscribe($user, 'monthly');
    }

    #[Test]
    public function it_throws_error_while_swapping_to_the_same_plan(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isBillingSubscribed')->andReturn(true);
        $user->shouldReceive('billingPlan')->andReturn('yearly');

        $service = new SubscriptionService;

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('You are already on this plan.');

        $service->swap($user, 'yearly');
    }

    #[Test]
    public function it_throws_exception_for_canceling_a_non_subscribed_plan(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isBillingSubscribed')->andReturn(true);
        $user->shouldReceive('billingPlan')->andReturn('monthly');

        $service = new SubscriptionService;

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('You are not subscribed to this plan.');

        $service->cancel($user, 'yearly');
    }

    #[Test]
    public function it_throws_exception_for_swapping_without_a_valid_subscription(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isBillingSubscribed')->andReturn(false);

        $service = new SubscriptionService;

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('You are not subscribed to a paid plan.');

        $service->swap($user, 'yearly');
    }
}
