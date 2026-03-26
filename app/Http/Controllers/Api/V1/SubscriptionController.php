<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\SubscriptionRequest;
use App\Http\Resources\Api\V1\SubscriptionResource;
use App\Interfaces\Paddle;
use App\Services\Api\V1\Subscription\PlanLimitService;
use App\Services\Api\V1\Subscription\SubscriptionCatalogService;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends ApiController
{
    public function __construct(
        private readonly PlanLimitService $planLimitService,
        private readonly SubscriptionCatalogService $subscriptionCatalogService,
    ) {}

    public function subscribe(Paddle $paddle, SubscriptionRequest $request): JsonResponse
    {
        $payLink = $paddle->subscribe($this->authenticatedUser(), (string) $request->string('plan')->trim());

        return response()->json([
            'paylink' => $payLink,
        ], 200);
    }

    public function subscriptions(): JsonResponse
    {
        $user = $this->authenticatedUser();

        return response()->json([
            'subscription' => new SubscriptionResource(
                $user,
                $this->planLimitService->plan($user),
                $this->planLimitService->accountUsage($user),
                $this->subscriptionCatalogService->availablePlans(),
            ),
        ], 200);
    }

    public function swap(Paddle $paddle, SubscriptionRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $result = $paddle->swap($user, (string) $request->string('plan')->trim());

        return response()->json([
            'message' => $result['message'],
            'subscription' => new SubscriptionResource(
                $user,
                $this->planLimitService->plan($user),
                $this->planLimitService->accountUsage($user),
                $this->subscriptionCatalogService->availablePlans(),
            ),
        ], 200);
    }

    public function cancel(Paddle $paddle, SubscriptionRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $result = $paddle->cancel($user, (string) $request->string('plan')->trim());

        return response()->json([
            'message' => $result['message'],
            'subscription' => new SubscriptionResource(
                $user,
                $this->planLimitService->plan($user),
                $this->planLimitService->accountUsage($user),
                $this->subscriptionCatalogService->availablePlans(),
            ),
        ], 200);
    }
}
