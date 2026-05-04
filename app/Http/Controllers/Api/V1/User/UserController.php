<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\UserRequest;
use App\Http\Resources\Api\V1\FeatureFlagsResource;
use App\Http\Resources\Api\V1\User\AuthenticatedUserResource;
use App\Http\Resources\Api\V1\User\UserProfileResource;
use App\Http\Resources\Api\V1\User\UserSummaryResource;
use App\Models\User;
use App\Services\Api\V1\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends ApiController
{
    /**
     * List all users
     *
     * This endpoint returns a list of all users in the application.
     */
    public function index(): JsonResponse
    {
        $users = User::query()->get();

        return UserSummaryResource::collection($users)->response();
    }

    /**
     * Get the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $user->loadMissing('twoFactorAuth');

        return $this->respondWithData([
            'user' => (new AuthenticatedUserResource($user))->resolve($request),
            'features' => (new FeatureFlagsResource($user))->resolve($request),
        ], Response::HTTP_OK);
    }

    /**
     * Show user details
     *
     * Get detailed information for a specific user.
     */
    public function show(User $user): JsonResponse
    {
        $user->loadMissing('info');

        return (new UserProfileResource($user))->response();
    }

    /**
     * Update user
     *
     * Update the specified user's information. Only the owner can update their data.
     */
    public function update(UserRequest $request, User $user, UserService $userService): JsonResponse
    {
        $this->authorize('owner', $user);

        $userService->updateUser($user, $request->validated());

        $updatedUser = $user->fresh(['info']);

        return $this->respondUpdated(new UserProfileResource($updatedUser));
    }

    /**
     * Soft delete user
     *
     * Soft delete the specified user. Only the owner can delete their account.  * This will also soft delete all projects owned by the user*.
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('owner', $user);

        $user->delete(); // Soft delete

        return $this->respondWithMessage('User data deleted successfully.');
    }
}
