<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Http\Resources\Api\V1\SubscriptionResource;
use App\Models\User;
use Carbon\CarbonInterface;
use Laravel\Paddle\Payment;
use Laravel\Paddle\Subscription as PaddleSubscription;

/**
 * Composes the subscription response payload returned by the API.
 */
final readonly class SubscriptionViewService
{
    public function __construct(
        private PlanLimitService $planLimitService,
        private SubscriptionUsageService $subscriptionUsageService,
        private SubscriptionCatalogService $subscriptionCatalogService,
    ) {}

    /**
     * Hydrates the resource with the current plan, usage, and purchasable plans.
     */
    public function createFor(User $user): SubscriptionResource
    {
        $user = $user->loadMissing(['receipts', 'subscriptions', 'customer']);

        $subscription = $user->getSubscription();
        $isBillingSubscribed = $subscription?->recurring() === true;
        $hasGracePeriod = $subscription?->onGracePeriod() === true;
        $isBillingVisible = $subscription !== null && ($isBillingSubscribed || $hasGracePeriod);
        $isOnTrial = $user->isOnTrial();

        return new SubscriptionResource(
            $user,
            $this->planLimitService->plan($user),
            $this->subscriptionUsageService->accountUsage($user),
            $this->subscriptionCatalogService->availablePlans(),
            $this->resolveBillingState(
                user: $user,
                subscription: $subscription,
                isBillingSubscribed: $isBillingSubscribed,
                isBillingVisible: $isBillingVisible,
            ),
            [
                'active' => $isOnTrial,
                'ends_at' => $isOnTrial
                    ? $user->trialEndsAt($user->subscriptionName()) ?? $user->trialEndsAt()
                    : null,
            ],
            [
                'active' => $hasGracePeriod,
                'ends_at' => $hasGracePeriod ? $subscription?->ends_at : null,
            ],
        );
    }

    /**
     * @return array{subscribed: bool, billing_plan: ?string, next_payment: ?Payment, created_at: ?CarbonInterface}
     */
    private function resolveBillingState(
        User $user,
        ?PaddleSubscription $subscription,
        bool $isBillingSubscribed,
        bool $isBillingVisible,
    ): array {
        return [
            'subscribed' => $isBillingSubscribed,
            'billing_plan' => $isBillingVisible ? $user->displayBillingPlan() : null,
            'next_payment' => $isBillingSubscribed ? $user->payment() : null,
            'created_at' => $isBillingSubscribed ? $subscription?->created_at : null,
        ];
    }
}
