<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\SubscriptionRequest;
use App\Interfaces\Paddle;
use App\Services\Subscription\SubscriptionViewService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends ApiController
{
    public function __construct(private readonly SubscriptionViewService $subscriptionViewService) {}

    /**
     * Generate a subscription pay link.
     *
     * Creates the checkout URL for the selected subscription plan. Available plans: free, pro.
     */
    #[Endpoint(operationId: 'subscription.checkout')]
    #[ScrambleResponse(
        status: 200,
        description: 'Subscription checkout URL returned for the selected plan.',
        type: 'array{data: array{paylink: string}}',
    )]
    public function store(Paddle $paddle, SubscriptionRequest $request): JsonResponse
    {
        $data = $request->toDto();
        $payLink = $paddle->subscribe($this->authenticatedUser(), $data->plan);

        return $this->respondWithData([
            'paylink' => $payLink,
        ], Response::HTTP_OK);
    }

    /**
     * Get the authenticated user's subscription details.
     *
     * Returns the current subscription snapshot for the authenticated user.
     */
    #[Endpoint(operationId: 'subscription.status')]
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
     * Plan swaps take effect immediately. Available plans: free, pro.
     */
    #[Endpoint(operationId: 'subscription.update')]
    public function update(Paddle $paddle, SubscriptionRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $data = $request->toDto();
        $paddle->swap($user, $data->plan);

        return $this->respondWithData(
            $this->subscriptionViewService->createFor($user),
            Response::HTTP_OK,
        );
    }

    /**
     * Cancel subscription.
     *
     * Cancels the authenticated user's subscription and returns the updated subscription snapshot.
     * Cancellation takes effect at the end of the current billing cycle.
     */
    #[Endpoint(operationId: 'subscription.cancel')]
    public function destroy(Paddle $paddle, SubscriptionRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $data = $request->toDto();
        $paddle->cancel($user, $data->plan);

        return $this->respondWithData(
            $this->subscriptionViewService->createFor($user),
            Response::HTTP_OK,
        );
    }
}
