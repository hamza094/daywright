<?php

declare(strict_types=1);

namespace App\Traits;

trait HasSubscription
{
    private const SUBSCRIPTION_NAME = 'DayWright';

    /**
     * Determine whether the user has an active DayWright subscription.
     *
     * @return bool `true` if the user has an active DayWright subscription, `false` otherwise.
     */
    public function isSubscribed(): bool
    {
        return (bool) $this->getSubscription();
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
     * Determine whether the user's DayWright subscription is currently in its grace period.
     *
     * @return bool `true` if the subscription exists and is in a grace period, `false` otherwise.
     */
    public function hasGracePeriod(): bool
    {
        $subscription = $this->getSubscription();

        return $subscription ? $subscription->onGracePeriod() : false;
    }

    /**
     * Retrieve the user's next payment for the DayWright subscription or indicate absence of an active subscription.
     *
     * @return mixed The subscription's next payment value if subscribed; otherwise the string 'No active subscription'.
     */
    public function payment(): mixed
    {
        $subscription = $this->getSubscription();

        return $subscription ? $subscription->nextPayment() : 'No active subscription';
    }

    /**
     * Retrieve the user's DayWright subscription instance.
     *
     * @return mixed The subscription instance for "DayWright", or `null` if not found.
     */
    public function getSubscription(): mixed
    {
        return $this->subscription(self::SUBSCRIPTION_NAME);
    }
}