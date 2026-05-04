<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Subscription\PlanLimitType;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\UserTokenRequest;
use App\Http\Resources\Api\V1\TokenResource;
use App\Models\User;
use App\Services\Api\V1\Subscription\PlanLimitService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TokenController extends ApiController
{
    /**
     * List all personal access tokens
     *
     * This endpoint returns all personal access tokens for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $tokens = $this->authenticatedUser()->tokens;

        return TokenResource::collection($tokens)->response();
    }

    /**
     * Create a new personal access token
     *
     * This endpoint creates a new personal access token for the authenticated user.
     */
    public function store(UserTokenRequest $request, PlanLimitService $planLimitService): JsonResponse
    {
        $data = $request->validated();
        $expiresAt = ! empty($data['expires_at']) ? Carbon::parse($data['expires_at']) : null;

        $token = $planLimitService->executeWithinAccountLimit(
            PlanLimitType::ApiTokens,
            $this->authenticatedUser(),
            fn (User $user) => $user->createToken(
                $data['name'],
                ['*'],
                $expiresAt
            )
        );

        return $this->respondWithData([
            'token' => $token->plainTextToken,
            'token_resource' => (new TokenResource($token->accessToken))->resolve(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Delete a personal access token
     *
     * This endpoint deletes a personal access token by ID for the authenticated user. Cannot delete the current session token via this route.
     */
    public function destroy(int $tokenId): JsonResponse
    {
        $user = $this->authenticatedUser();
        $currentToken = $user->currentAccessToken();

        // @phpstan-ignore-next-line
        abort_if(! $currentToken, Response::HTTP_FORBIDDEN, 'No current access token found.');

        /** @var \Laravel\Sanctum\PersonalAccessToken $currentToken */
        abort_if($currentToken->id === $tokenId, Response::HTTP_FORBIDDEN, 'Cannot delete the current session token via this route.');

        $deleted = $user->tokens()->where('id', $tokenId)->delete();

        abort_if(! $deleted, Response::HTTP_NOT_FOUND, 'Token not found.');

        return $this->respondWithMessage('Token deleted.');
    }
}
