<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\UserTokenRequest;
use App\Http\Resources\Api\V1\TokenResource;
use App\Services\Auth\ApiTokenService;
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
        $tokens = app(ApiTokenService::class)->listForUser($this->authenticatedUser());

        return TokenResource::collection($tokens)->response();
    }

    /**
     * Create a new personal access token
     *
     * This endpoint creates a new personal access token for the authenticated user.
     */
    public function store(UserTokenRequest $request, ApiTokenService $apiTokenService): JsonResponse
    {
        $data = $request->validated();
        $expiresAt = ! empty($data['expires_at']) ? Carbon::parse($data['expires_at']) : null;

        $token = $apiTokenService->createForUser($this->authenticatedUser(), $data['name'], $expiresAt);

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
        app(ApiTokenService::class)->deleteForUser($this->authenticatedUser(), $tokenId);

        return $this->respondWithMessage('Token deleted successfully.');
    }
}
