<?php

declare(strict_types=1);

namespace App\Traits;

trait HasSubscription
{
    public function subscriptionName(): string
    {
        return (string) config('services.paddle.subscription_name');
    }

    public function isOnTrial(): bool
    {
        return $this->onTrial() || $this->onTrial($this->subscriptionName());
    }

    public function isInGracePeriod(): bool
    {
        return $this->getSubscription()?->onGracePeriod() === true;
    }

    public function hasSubscriptionRecord(): bool
    {
        return $this->getSubscription() !== null;
    }

    /**
     * Check if the user has a valid DayWright subscription record.
     */
    public function isSubscribed(): bool
    {
        $subscription = $this->getSubscription();

        return $subscription?->valid() === true;
    }

    /**
     * Check if the user has an actively recurring DayWright subscription.
     */
    public function isBillingSubscribed(): bool
    {
        $subscription = $this->getSubscription();

        return $subscription?->recurring() === true;
    }

    /**
     * Get the user's current recurring billing plan name ('monthly', 'yearly', 'Not Subscribed', or 'Unknown').
     * Optionally accepts plan IDs for testability.
     */
    public function billingPlan(?int $monthlyPlanId = null, ?int $yearlyPlanId = null): string
    {
        $subscription = $this->getSubscription();

        if ($subscription?->recurring() !== true) {
            return 'Not Subscribed';
        }

        $monthlyPlanId ??= (int) config('services.paddle.monthly');
        $yearlyPlanId ??= (int) config('services.paddle.yearly');

        $paddlePlan = $subscription->paddle_plan ?? null;

        return match ($paddlePlan) {
            $monthlyPlanId => 'monthly',
            $yearlyPlanId => 'yearly',
            default => 'Unknown',
        };
    }

    /**
     * Determine if the user's subscription is in a grace period.
     */
    public function hasGracePeriod(): bool
    {
        return $this->isInGracePeriod();
    }

    /**
     * Get the user's next scheduled payment for the DayWright subscription.
     */
    public function payment(): mixed
    {
        $subscription = $this->getSubscription();

        if ($subscription?->valid() !== true) {
            return null;
        }

        return $subscription->nextPayment();
    }

    /**
     * Helper to get the DayWright subscription record, even if it is no longer valid.
     */
    public function getSubscription(): mixed
    {
        return $this->subscription($this->subscriptionName());
    }
}
