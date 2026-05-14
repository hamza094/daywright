<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\SubscriptionRequest;
use App\Interfaces\Paddle;
use App\Services\Subscription\SubscriptionViewService;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends ApiController
{
    public function __construct(private readonly SubscriptionViewService $subscriptionViewService) {}

    /**
     * Generate a subscription pay link.
     *
     * Creates the checkout URL for the selected subscription plan.
     *
     * @operationId createSubscription
     *
     * @tags Subscription
     */
    #[ScrambleResponse(
        status: 200,
        description: 'Subscription checkout URL returned for the selected plan.',
        type: 'array{data: array{paylink: string}}',
    )]
    public function store(Paddle $paddle, SubscriptionRequest $request): JsonResponse
    {
        $payLink = $paddle->subscribe($this->authenticatedUser(), (string) $request->string('plan')->trim());

        return $this->respondWithData([
            'paylink' => $payLink,
        ], Response::HTTP_OK);
    }

    /**
     * Get the authenticated user's subscription details.
     *
     * Returns the current subscription snapshot for the authenticated user.
     *
     * @operationId showSubscription
     *
     * @tags Subscription
     */
    public function show(): JsonResponse
    {
        $user = $this->authenticatedUser();

        return $this->respondWithData(
            $this->subscriptionViewService->createFor($user),
            Response::HTTP_OK,
        );
    }

    /**
     * Swap subscription plan.
     *
     * Changes the authenticated user's subscription to a different supported plan.
     *
     * @operationId updateSubscription
     *
     * @tags Subscription
     */
    public function update(Paddle $paddle, SubscriptionRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $paddle->swap($user, (string) $request->string('plan')->trim());

        return $this->respondWithData(
            $this->subscriptionViewService->createFor($user),
            Response::HTTP_OK,
        );
    }

    /**
     * Cancel subscription.
     *
     * Cancels the authenticated user's subscription and returns the updated subscription snapshot.
     *
     * @operationId cancelSubscription
     *
     * @tags Subscription
     */
    public function destroy(Paddle $paddle, SubscriptionRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $paddle->cancel($user, (string) $request->string('plan')->trim());

        return $this->respondWithData(
            $this->subscriptionViewService->createFor($user),
            Response::HTTP_OK,
        );
    }
}
