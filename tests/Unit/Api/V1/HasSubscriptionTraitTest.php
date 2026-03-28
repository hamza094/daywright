<?php

declare(strict_types=1);

namespace Tests\Unit\Api\V1;

use PHPUnit\Framework\TestCase;
use Tests\Traits\DummyUserWithSubscription;

class HasSubscriptionTraitTest extends TestCase
{
    public function test_resolve_billing_plan_name_does_not_treat_non_numeric_paddle_plan_as_zero(): void
    {
        $user = new DummyUserWithSubscription;

        $this->assertSame('Unknown', $user->resolveBillingPlanName('invalid-plan', 0, 456));
    }

    public function test_is_subscribed_returns_true_when_subscription_is_valid(): void
    {
        $user = new DummyUserWithSubscription;
        $user->mockSubscription = new class
        {
            public function valid(): bool
            {
                return true;
            }
        };
        $this->assertTrue($user->isSubscribed());
    }

    public function test_is_subscribed_returns_false_when_subscription_is_not_valid(): void
    {
        $user = new DummyUserWithSubscription;
        $user->mockSubscription = new class
        {
            public function valid(): bool
            {
                return false;
            }
        };
        $this->assertFalse($user->isSubscribed());
    }

    public function test_is_subscribed_returns_false_when_no_subscription(): void
    {
        $user = new DummyUserWithSubscription;
        $user->mockSubscription = null;
        $this->assertFalse($user->isSubscribed());
    }

    public function test_is_billing_subscribed_returns_true_only_when_subscription_is_recurring(): void
    {
        $user = new DummyUserWithSubscription;
        $user->mockSubscription = new class
        {
            public function recurring(): bool
            {
                return true;
            }
        };
        $this->assertTrue($user->isBillingSubscribed());

        $user->mockSubscription = new class
        {
            public function recurring(): bool
            {
                return false;
            }
        };
        $this->assertFalse($user->isBillingSubscribed());

        $user->mockSubscription = null;
        $this->assertFalse($user->isBillingSubscribed());
    }

    public function test_billing_plan_variants(): void
    {
        $user = new DummyUserWithSubscription;
        $user->mockSubscription = null;
        $this->assertEquals('Not Subscribed Actively', $user->activeBillingPlan());

        $user->mockSubscription = new class
        {
            public int $paddle_plan = 123;

            public function recurring(): bool
            {
                return false;
            }
        };
        $this->assertEquals('Not Subscribed Actively', $user->activeBillingPlan());
        $this->assertEquals('monthly', $user->displayBillingPlan($monthlyPlanId = 123, yearlyPlanId: 456));

        $user->mockSubscription = new class($monthlyPlanId)
        {
            public function __construct(public int $paddle_plan) {}

            public function recurring(): bool
            {
                return true;
            }
        };
        $this->assertEquals('monthly', $user->activeBillingPlan($monthlyPlanId, 456));

        $yearlyPlanId = 456;
        $user->mockSubscription = new class($yearlyPlanId)
        {
            public function __construct(public int $paddle_plan) {}

            public function recurring(): bool
            {
                return true;
            }
        };
        $this->assertEquals('yearly', $user->activeBillingPlan($monthlyPlanId, $yearlyPlanId));

        $user->mockSubscription = new class
        {
            public int $paddle_plan = 99999;

            public function recurring(): bool
            {
                return true;
            }
        };
        $this->assertEquals('Unknown', $user->activeBillingPlan($monthlyPlanId, $yearlyPlanId));
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

    public function test_payment_returns_next_payment_or_null(): void
    {
        $user = new DummyUserWithSubscription;
        // Next payment
        $user->mockSubscription = new class
        {
            public function valid(): bool
            {
                return true;
            }

            public function nextPayment(): string
            {
                return 'next payment date';
            }
        };
        $this->assertEquals('next payment date', $user->payment());
        // Valid subscription with no upcoming payment
        $user->mockSubscription = new class
        {
            public function valid(): bool
            {
                return true;
            }

            public function nextPayment(): ?string
            {
                return null;
            }
        };
        $this->assertNull($user->payment());
        // Invalid subscription
        $user->mockSubscription = new class
        {
            public function valid(): bool
            {
                return false;
            }

            public function nextPayment(): string
            {
                return 'stale payment date';
            }
        };
        $this->assertNull($user->payment());
        // No active subscription
        $user->mockSubscription = null;
        $this->assertNull($user->payment());
    }
}
