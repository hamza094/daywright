<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\CreateApiTokenAction;
use App\Actions\Auth\RevokeApiTokenAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\User\UserTokenRequest;
use App\Http\Resources\Api\V1\TokenResource;
use App\Http\Resources\Api\V1\TokenStoreResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TokenController extends ApiController
{
    public function __construct(
        private readonly CreateApiTokenAction $createApiTokenAction,
        private readonly RevokeApiTokenAction $revokeApiTokenAction
    ) {}

    /**
     * List all personal access tokens
     *
     * Returns the authenticated user's existing personal access tokens.
     */
    public function index(): JsonResponse
    {
        $tokens = $this->authenticatedUser()->tokens;

        return TokenResource::collection($tokens)->response();
    }

    /**
     * Create a new personal access token
     *
     * Creates a new personal access token for the authenticated user.
     */
    public function store(UserTokenRequest $request): JsonResponse
    {
        $data = $request->toDto();

        $token = $this->createApiTokenAction->execute(
            $this->authenticatedUser(),
            $data->name,
            $data->scopes,
            $data->expires_at
        );

        return $this->respondWithData(
            new TokenStoreResource($token->plainTextToken, $token->accessToken),
            Response::HTTP_CREATED,
        );
    }

    /**
     * Delete a personal access token
     *
     * Deletes a personal access token by ID for the authenticated user.
     * The current token used for this request cannot be deleted through this route.
     */
    public function destroy(int $token): JsonResponse
    {
        $this->revokeApiTokenAction->execute($this->authenticatedUser(), $token);

        return $this->respondWithMessage('Token deleted successfully.');
    }
}
