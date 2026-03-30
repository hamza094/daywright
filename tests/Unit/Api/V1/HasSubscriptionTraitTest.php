<?php

declare(strict_types=1);

namespace Tests\Unit\Api\V1;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\DummyUserWithSubscription;

final class HasSubscriptionTraitTest extends TestCase
{
    private const MONTHLY_PLAN_ID = 123;

    private const YEARLY_PLAN_ID = 456;

    #[Test]
    public function resolve_billing_plan_name_does_not_treat_non_numeric_paddle_plan_as_zero(): void
    {
        $user = $this->makeUser();

        $this->assertSame(
            'Unknown',
            $user->resolveBillingPlanName('invalid-plan', self::MONTHLY_PLAN_ID, self::YEARLY_PLAN_ID),
        );
    }

    #[Test]
    public function is_on_trial_returns_true_for_generic_or_named_trials(): void
    {
        $userWithoutTrial = $this->makeUser();
        $userOnGenericTrial = $this->makeUser(genericTrial: true);
        $userOnNamedTrial = $this->makeUser(namedTrial: true);

        $this->assertFalse($userWithoutTrial->isOnTrial());
        $this->assertTrue($userOnGenericTrial->isOnTrial());
        $this->assertTrue($userOnNamedTrial->isOnTrial());
    }

    #[Test]
    public function subscription_state_helpers_reflect_missing_invalid_and_valid_subscriptions(): void
    {
        $missingSubscriptionUser = $this->makeUser();
        $invalidSubscriptionUser = $this->makeUser(subscription: $this->makeInvalidSubscription());
        $validRecurringSubscriptionUser = $this->makeUser(subscription: $this->makeValidRecurringSubscription());

        $this->assertFalse($missingSubscriptionUser->hasSubscriptionRecord());
        $this->assertFalse($missingSubscriptionUser->isSubscribed());
        $this->assertFalse($missingSubscriptionUser->isBillingSubscribed());

        $this->assertTrue($invalidSubscriptionUser->hasSubscriptionRecord());
        $this->assertFalse($invalidSubscriptionUser->isSubscribed());
        $this->assertFalse($invalidSubscriptionUser->isBillingSubscribed());

        $this->assertTrue($validRecurringSubscriptionUser->hasSubscriptionRecord());
        $this->assertTrue($validRecurringSubscriptionUser->isSubscribed());
        $this->assertTrue($validRecurringSubscriptionUser->isBillingSubscribed());
    }

    #[Test]
    public function active_billing_plan_returns_expected_values(): void
    {
        $userWithoutSubscription = $this->makeUser();
        $userWithCanceledMonthlyPlan = $this->makeUser(subscription: $this->makeCanceledSubscription(self::MONTHLY_PLAN_ID));
        $userWithRecurringMonthlyPlan = $this->makeUser(subscription: $this->makeValidRecurringSubscription(self::MONTHLY_PLAN_ID));
        $userWithRecurringYearlyPlan = $this->makeUser(subscription: $this->makeValidRecurringSubscription(self::YEARLY_PLAN_ID));
        $userWithUnknownRecurringPlan = $this->makeUser(subscription: $this->makeValidRecurringSubscription(99999));

        $this->assertSame('Not Subscribed Actively', $this->activeBillingPlan($userWithoutSubscription));
        $this->assertSame('Not Subscribed Actively', $this->activeBillingPlan($userWithCanceledMonthlyPlan));
        $this->assertSame('monthly', $this->activeBillingPlan($userWithRecurringMonthlyPlan));
        $this->assertSame('yearly', $this->activeBillingPlan($userWithRecurringYearlyPlan));
        $this->assertSame('Unknown', $this->activeBillingPlan($userWithUnknownRecurringPlan));
    }

    #[Test]
    public function display_billing_plan_returns_expected_values(): void
    {
        $userWithoutSubscription = $this->makeUser();
        $userWithCanceledMonthlyPlan = $this->makeUser(subscription: $this->makeCanceledSubscription(self::MONTHLY_PLAN_ID));
        $userWithRecurringYearlyPlan = $this->makeUser(subscription: $this->makeValidRecurringSubscription(self::YEARLY_PLAN_ID));
        $userWithUnknownRecurringPlan = $this->makeUser(subscription: $this->makeValidRecurringSubscription(99999));

        $this->assertSame('Not Subscribed Actively', $this->displayBillingPlan($userWithoutSubscription));
        $this->assertSame('monthly', $this->displayBillingPlan($userWithCanceledMonthlyPlan));
        $this->assertSame('yearly', $this->displayBillingPlan($userWithRecurringYearlyPlan));
        $this->assertSame('Unknown', $this->displayBillingPlan($userWithUnknownRecurringPlan));
    }

    #[Test]
    public function has_grace_period_reflects_the_subscription_state(): void
    {
        $userWithoutSubscription = $this->makeUser();
        $userInGracePeriod = $this->makeUser(subscription: $this->makeGracePeriodSubscription(true));
        $userOutsideGracePeriod = $this->makeUser(subscription: $this->makeGracePeriodSubscription(false));

        $this->assertFalse($userWithoutSubscription->hasGracePeriod());
        $this->assertTrue($userInGracePeriod->hasGracePeriod());
        $this->assertFalse($userOutsideGracePeriod->hasGracePeriod());
    }

    #[Test]
    public function payment_returns_the_next_payment_only_for_valid_subscriptions(): void
    {
        $userWithoutSubscription = $this->makeUser();
        $userWithInvalidSubscription = $this->makeUser(subscription: $this->makePaymentSubscription(false, 'stale payment date'));
        $userWithUpcomingPayment = $this->makeUser(subscription: $this->makePaymentSubscription(true, 'next payment date'));
        $userWithoutUpcomingPayment = $this->makeUser(subscription: $this->makePaymentSubscription(true, null));

        $this->assertNull($userWithoutSubscription->payment());
        $this->assertNull($userWithInvalidSubscription->payment());
        $this->assertSame('next payment date', $userWithUpcomingPayment->payment());
        $this->assertNull($userWithoutUpcomingPayment->payment());
    }

    private static function makeSubscription(
        ?bool $valid = null,
        ?bool $recurring = null,
        ?bool $onGracePeriod = null,
        ?int $paddlePlan = null,
        mixed $nextPayment = null,
    ): object {
        return new class($valid, $recurring, $onGracePeriod, $paddlePlan, $nextPayment)
        {
            public function __construct(
                private readonly ?bool $valid,
                private readonly ?bool $recurring,
                private readonly ?bool $onGracePeriod,
                public ?int $paddle_plan,
                private readonly mixed $nextPayment,
            ) {}

            public function valid(): bool
            {
                return $this->valid ?? false;
            }

            public function recurring(): bool
            {
                return $this->recurring ?? false;
            }

            public function onGracePeriod(): bool
            {
                return $this->onGracePeriod ?? false;
            }

            public function nextPayment(): mixed
            {
                return $this->nextPayment;
            }
        };
    }

    private function makeInvalidSubscription(): object
    {
        return self::makeSubscription(valid: false, recurring: false);
    }

    private function makeValidRecurringSubscription(?int $paddlePlan = null): object
    {
        return self::makeSubscription(valid: true, recurring: true, paddlePlan: $paddlePlan);
    }

    private function makeCanceledSubscription(int $paddlePlan): object
    {
        return self::makeSubscription(valid: true, recurring: false, paddlePlan: $paddlePlan);
    }

    private function makeGracePeriodSubscription(bool $onGracePeriod): object
    {
        return self::makeSubscription(onGracePeriod: $onGracePeriod);
    }

    private function makePaymentSubscription(bool $isValid, mixed $nextPayment): object
    {
        return self::makeSubscription(valid: $isValid, nextPayment: $nextPayment);
    }

    private function activeBillingPlan(DummyUserWithSubscription $user): string
    {
        return $user->activeBillingPlan(self::MONTHLY_PLAN_ID, self::YEARLY_PLAN_ID);
    }

    private function displayBillingPlan(DummyUserWithSubscription $user): string
    {
        return $user->displayBillingPlan(self::MONTHLY_PLAN_ID, self::YEARLY_PLAN_ID);
    }

    private function makeUser(
        ?object $subscription = null,
        bool $genericTrial = false,
        bool $namedTrial = false,
    ): DummyUserWithSubscription {
        return new DummyUserWithSubscription(
            subscription: $subscription,
            genericTrial: $genericTrial,
            namedTrial: $namedTrial,
        );
    }
}
