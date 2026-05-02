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

        return response()->json([
            'users' => UserSummaryResource::collection($users),
        ], 200);
    }

    /**
     * Get the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user?->loadMissing('twoFactorAuth');

        return response()->json([
            'message' => 'Authenticated user data',
            'user' => $user ? new AuthenticatedUserResource($user) : null,
            'features' => new FeatureFlagsResource($user),
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

        return response()->json([
            'message' => 'User Data',
            'user' => new UserProfileResource($user),
        ], 200);
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

        return response()->json([
            'message' => 'User data updated successfully.',
            'user' => new UserProfileResource($updatedUser),
        ], 200);
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

        return response()->json([
            'message' => 'User data deleted successfully.',
        ], 200);
    }
}
