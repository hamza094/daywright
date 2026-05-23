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

        /** @var ?PaddleSubscription $subscription */
        $subscription = $user->getSubscription();
        // @phpstan-ignore-next-line - getSubscription() phpdoc may be overly-certain about nullability
        $isBillingSubscribed = $subscription !== null && $subscription->recurring() === true;
        // @phpstan-ignore-next-line - getSubscription() phpdoc may be overly-certain about nullability
        $hasGracePeriod = $subscription !== null && $subscription->onGracePeriod() === true;
        $isBillingVisible = $isBillingSubscribed || $hasGracePeriod;
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
                // @phpstan-ignore-next-line - getSubscription() phpdoc may be overly-certain about nullability
                'ends_at' => $hasGracePeriod && $subscription !== null ? $subscription->ends_at : null,
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
        $createdAt = null;
        // @phpstan-ignore-next-line - getSubscription() phpdoc may be overly-certain about nullability
        if ($isBillingSubscribed && $subscription !== null) {
            $createdAt = $subscription->created_at;
        }

        return [
            'subscribed' => $isBillingSubscribed,
            'billing_plan' => $isBillingVisible ? $user->displayBillingPlan() : null,
            'next_payment' => $isBillingSubscribed ? $user->payment() : null,
            'created_at' => $createdAt,
        ];
    }
}
