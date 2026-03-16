<?php

declare(strict_types=1);

namespace Tests\Unit\Api\V1;

use App\Traits\HasSubscription;
use PHPUnit\Framework\TestCase;

class DummyUserWithSubscription
{
    use HasSubscription;

    public $mockSubscription;

    // Provide a stable subscription name for unit tests (avoids calling the global config() helper)
    public function subscriptionName(): string
    {
        return 'DayWright';
    }

    public function subscription($name)
    {
        return $this->mockSubscription;
    }
}

class HasSubscriptionTest extends TestCase
{
    public function test_is_subscribed_returns_true_when_subscription_exists(): void
    {
        $user = new DummyUserWithSubscription;
        $user->mockSubscription = 'fakeSubscription';
        $this->assertTrue($user->isSubscribed());
    }

    public function test_is_subscribed_returns_false_when_no_subscription(): void
    {
        $user = new DummyUserWithSubscription;
        $user->mockSubscription = null;
        $this->assertFalse($user->isSubscribed());
    }

    public function test_subscribed_plan_variants(): void
    {
        $user = new DummyUserWithSubscription;
        // Not Subscribed
        $user->mockSubscription = null;
        $this->assertEquals('Not Subscribed', $user->subscribedPlan());
        // Monthly
        $monthlyPlanId = 123;
        $user->mockSubscription = (object) ['paddle_plan' => $monthlyPlanId];
        $this->assertEquals('monthly', $user->subscribedPlan($monthlyPlanId, 456));
        // Yearly
        $yearlyPlanId = 456;
        $user->mockSubscription = (object) ['paddle_plan' => $yearlyPlanId];
        $this->assertEquals('yearly', $user->subscribedPlan($monthlyPlanId, $yearlyPlanId));
        // Unknown
        $user->mockSubscription = (object) ['paddle_plan' => 99999];
        $this->assertEquals('Unknown', $user->subscribedPlan($monthlyPlanId, $yearlyPlanId));
    }

    public function test_has_grace_period_true_and_false(): void
    {
        $user = new DummyUserWithSubscription;
        // True
        $user->mockSubscription = new class
        {
            public function onGracePeriod(): bool
            {
                return true;
            }
        };
        $this->assertTrue($user->hasGracePeriod());
        // False
        $user->mockSubscription = new class
        {
            public function onGracePeriod(): bool
            {
                return false;
            }
        };
        $this->assertFalse($user->hasGracePeriod());
        // No subscription
        $user->mockSubscription = null;
        $this->assertFalse($user->hasGracePeriod());
    }

    public function test_payment_returns_next_payment_and_no_active_subscription(): void
    {
        $user = new DummyUserWithSubscription;
        // Next payment
        $user->mockSubscription = new class
        {
            public function nextPayment(): string
            {
                return 'next payment date';
            }
        };
        $this->assertEquals('next payment date', $user->payment());
        // No active subscription
        $user->mockSubscription = null;
        $this->assertEquals('No active subscription', $user->payment());
    }
}
