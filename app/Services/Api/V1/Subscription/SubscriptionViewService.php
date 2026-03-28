<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Subscription;

use App\Http\Resources\Api\V1\SubscriptionResource;
use App\Models\User;

final readonly class SubscriptionViewService
{
    public function __construct(
        private PlanLimitService $planLimitService,
        private SubscriptionCatalogService $subscriptionCatalogService,
    ) {}

    public function createFor(User $user): SubscriptionResource
    {
        $user = $user->loadMissing('receipts');

        return new SubscriptionResource(
            $user,
            $this->planLimitService->plan($user),
            $this->planLimitService->accountUsage($user),
            $this->subscriptionCatalogService->availablePlans(),
        );
    }
}
