<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Subscription;

use App\Http\Resources\Api\V1\SubscriptionResource;
use App\Models\User;

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
        $user = $user->loadMissing('receipts');

        return new SubscriptionResource(
            $user,
            $this->planLimitService->plan($user),
            $this->subscriptionUsageService->accountUsage($user),
            $this->subscriptionCatalogService->availablePlans(),
        );
    }
}
