<?php

declare(strict_types=1);

namespace App\Traits;

trait HasSubscription
{
    private const SUBSCRIPTION_NAME = 'DayWright';

    /**
     * Check if the user is subscribed to the DayWright plan.
     */
    public function isSubscribed(): bool
    {
        return true;//return (bool) $this->getSubscription();
    }

    /**
     * Get the user's current subscription plan name ('monthly', 'yearly', 'Not Subscribed', or 'Unknown').
     * Optionally accepts plan IDs for testability.
     */
    public function subscribedPlan(?int $monthlyPlanId = null, ?int $yearlyPlanId = null): string
    {
        $subscription = $this->getSubscription();
        if (! $subscription) {
            return 'Not Subscribed';
        }
        $monthlyPlanId ??= (int) config('services.paddle.monthly');
        $yearlyPlanId ??= (int) config('services.paddle.yearly');
        $plans = [
            $monthlyPlanId => 'monthly',
            $yearlyPlanId => 'yearly',
        ];

        return $plans[$subscription->paddle_plan] ?? 'Unknown';
    }

    /**
     * Determine if the user's subscription is in a grace period.
     */
    public function hasGracePeriod(): bool
    {
        $subscription = $this->getSubscription();

        return $subscription ? $subscription->onGracePeriod() : false;
    }

    /**
     * Get the user's next payment for the DayWright subscription, or a message if not subscribed.
     */
    public function payment(): mixed
    {
        $subscription = $this->getSubscription();

        return $subscription ? $subscription->nextPayment() : 'No active subscription';
    }

    /**
     * Helper to get the DayWright subscription instance.
     */
    public function getSubscription(): mixed
    {
        return $this->subscription(self::SUBSCRIPTION_NAME);
    }
}
