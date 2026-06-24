<?php

declare(strict_types=1);

namespace App\Traits;

use Laravel\Paddle\Exceptions\PaddleException;
use LogicException;

/**
 * @method bool onTrial(?string $name = null)
 * @method mixed subscription(string $name)
 * @method mixed trialEndsAt(?string $name = null)
 */
trait HasSubscription
{
    public function subscriptionName(): string
    {
        $subscriptionName = config('services.paddle.subscription_name');

        if (! is_string($subscriptionName) || $subscriptionName === '') {
            throw new LogicException('services.paddle.subscription_name must be configured.');
        }

        return $subscriptionName;
    }

    public function isOnTrial(): bool
    {
        return $this->onTrial() || $this->onTrial($this->subscriptionName());
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
     * Only considers subscriptions with active or trialing status.
     */
    public function isBillingSubscribed(): bool
    {
        $subscription = $this->getSubscription();

        return $subscription !== null
            && $subscription->recurring() === true
            && in_array($subscription->paddle_status, ['active', 'trialing'], true);
    }

    /**
     * Get the active recurring billing plan name.
     * Only returns a plan name for subscriptions with active or trialing status.
     */
    public function activeBillingPlan(
        ?int $monthlyPlanId = null,
        ?int $yearlyPlanId = null,
    ): string {
        $subscription = $this->getSubscription();

        if (! $this->isBillingSubscribed()) {
            return 'Not Subscribed Actively';
        }

        return $this->resolveBillingPlanName(
            paddlePlan: $subscription->paddle_plan ?? null,
            monthlyPlanId: $monthlyPlanId,
            yearlyPlanId: $yearlyPlanId,
        );
    }

    /**
     * Get the billing plan name for an active subscription or a canceled one still in grace period.
     */
    public function displayBillingPlan(
        ?int $monthlyPlanId = null,
        ?int $yearlyPlanId = null,
    ): string {
        $subscription = $this->getSubscription();

        if ($subscription === null) {
            return 'Not Subscribed Actively';
        }

        return $this->resolveBillingPlanName(
            paddlePlan: $subscription->paddle_plan ?? null,
            monthlyPlanId: $monthlyPlanId,
            yearlyPlanId: $yearlyPlanId,
        );
    }

    /**
     * Determine if the user's subscription is in a grace period.
     */
    public function hasGracePeriod(): bool
    {
        return $this->getSubscription()?->onGracePeriod() === true;
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

        try {
            return $subscription->nextPayment();
        } catch (PaddleException) {
            return null;
        }
    }

    /**
     * Helper to get the DayWright subscription record, even if it is no longer valid.
     */
    public function getSubscription(): mixed
    {
        return $this->subscription($this->subscriptionName());
    }

    protected function resolveBillingPlanName(
        null|int|string $paddlePlan,
        ?int $monthlyPlanId = null,
        ?int $yearlyPlanId = null,
    ): string {
        $resolvedPaddlePlan = $this->resolveNullablePlanId($paddlePlan);
        $monthlyPlanId ??= $this->resolveNullablePlanId(config('services.paddle.monthly'));
        $yearlyPlanId ??= $this->resolveNullablePlanId(config('services.paddle.yearly'));

        return match (true) {
            $resolvedPaddlePlan !== null && $resolvedPaddlePlan === $monthlyPlanId => 'monthly',
            $resolvedPaddlePlan !== null && $resolvedPaddlePlan === $yearlyPlanId => 'yearly',
            default => 'Unknown',
        };
    }

    protected function resolveNullablePlanId(mixed $planId): ?int
    {
        $resolvedPlanId = filter_var($planId, FILTER_VALIDATE_INT);

        return $resolvedPlanId === false ? null : $resolvedPlanId;
    }
}
