<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PlanLimitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserTokenRequest;
use App\Http\Resources\Api\V1\TokenResource;
use App\Services\Api\V1\Subscription\PlanLimitService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class TokenController extends Controller
{
    /**
     * List all personal access tokens
     *
     * This endpoint returns all personal access tokens for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $tokens = auth()->user()->tokens;

        return response()->json([
            'tokens' => TokenResource::collection($tokens),
        ], 200);
    }

    /**
     * Create a new personal access token
     *
     * This endpoint creates a new personal access token for the authenticated user.
     */
    public function store(UserTokenRequest $request, PlanLimitService $planLimitService): JsonResponse
    {
        $planLimitService->assertWithinLimit(PlanLimitType::ApiTokens, auth()->user());

        $data = $request->validated();

        $token = auth()->user()->createToken(
            $data['name'],
            ['*'],
            Carbon::parse($data['expires_at'] ?? null)
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'token_resource' => new TokenResource($token->accessToken),
            'message' => 'Token created successfully.',
        ], 201);
    }

    /**
     * Delete a personal access token
     *
     * This endpoint deletes a personal access token by ID for the authenticated user. Cannot delete the current session token via this route.
     */
    public function destroy(int $tokenId): JsonResponse
    {
        $user = auth()->user();
        $currentToken = $user->currentAccessToken();

        // @phpstan-ignore-next-line
        if (! $currentToken) {
            return response()->json(['message' => 'No current access token found.'], 403);
        }

        /** @var \Laravel\Sanctum\PersonalAccessToken $currentToken */
        if ($currentToken->id === $tokenId) {
            return response()->json([
                'message' => 'Cannot delete the current session token via this route.',
            ], 403);
        }

        $deleted = $user->tokens()->where('id', $tokenId)->delete();

        return $deleted
            ? response()->json(['message' => 'Token deleted.'], 200)
            : response()->json(['message' => 'Token not found.'], 404);
    }
}
