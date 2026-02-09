<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\UserRequest;
use App\Http\Resources\Api\V1\FeatureFlagsResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Resources\Api\V1\UsersResource;
use App\Models\User;
use App\Services\Api\V1\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    /**
     * List all users
     *
     * This endpoint returns a list of all users in the application.
     */
    public function index(): JsonResponse
    {
        $users = User::all();

        return response()->json([
            'users' => UsersResource::collection($users),
        ], 200);
    }

    /**
     * Get the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'message' => 'Authenticated user data',
            'user' => $user ? new UsersResource($user) : null,
            'features' => new FeatureFlagsResource($user),
        ], 200);
    }

    /**
     * Show user details
     *
     * Get detailed information for a specific user.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'message' => 'User Data',
            'user' => new UserResource($user),
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

        return response()->json([
            'message' => 'User Data Updated Sucessfully',
            'user' => new UserResource($user),
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
            'message' => 'User Data Deleted Successfully',
        ], 200);
    }

    /**
     * Force delete user
     *
     * Permanently delete the specified user .
     */
    public function forceDestroy(User $user): JsonResponse
    {
        $this->authorize('owner', $user);

        $getUser = User::withTrashed()->findOrFail($user->id);
        $getUser->forceDelete();

        return response()->json([
            'message' => 'User Data Permanently Deleted',
        ], 200);
    }
}
