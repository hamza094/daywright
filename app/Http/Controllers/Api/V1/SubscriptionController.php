<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\SubscriptionRequest;
use App\Interfaces\Paddle;
use App\Services\Api\V1\Subscription\SubscriptionViewService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends ApiController
{
    public function __construct(private readonly SubscriptionViewService $subscriptionViewService) {}

    /**
     * Generate a subscription pay link.
     *
     * @operationId subscribe
     *
     * @tags Subscription
     */
    public function subscribe(Paddle $paddle, SubscriptionRequest $request): JsonResponse
    {
        $payLink = $paddle->subscribe($this->authenticatedUser(), (string) $request->string('plan')->trim());

        return response()->json([
            'paylink' => $payLink,
        ], Response::HTTP_OK);
    }

    /**
     * Get the authenticated user's subscription details.
     *
     * @operationId getSubscription
     *
     * @tags Subscription
     */
    public function subscriptions(): JsonResponse
    {
        $user = $this->authenticatedUser();

        return response()->json([
            'subscription' => $this->subscriptionViewService->createFor($user),
        ], Response::HTTP_OK);
    }

    /**
     * Swap subscription plan.
     *
     * @operationId swapSubscription
     *
     * @tags Subscription
     */
    public function swap(Paddle $paddle, SubscriptionRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $result = $paddle->swap($user, (string) $request->string('plan')->trim());

        return response()->json([
            'message' => $result['message'],
            'subscription' => $this->subscriptionViewService->createFor($user),
        ], Response::HTTP_OK);
    }

    /**
     * Cancel subscription.
     *
     * @operationId cancelSubscription
     *
     * @tags Subscription
     */
    public function cancel(Paddle $paddle, SubscriptionRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $result = $paddle->cancel($user, (string) $request->string('plan')->trim());

        return response()->json([
            'message' => $result['message'],
            'subscription' => $this->subscriptionViewService->createFor($user),
        ], Response::HTTP_OK);
    }
}
