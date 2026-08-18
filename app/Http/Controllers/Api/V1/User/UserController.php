<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\User\UserRequest;
use App\Http\Resources\Api\V1\User\UserProfileResource;
use App\Models\User;
use App\Services\User\UserService;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;

class UserController extends ApiController
{
    public function __construct(private readonly UserService $userService) {}

    /**
     * Show user details
     *
     * Get detailed information for a specific user.
     */
    #[Endpoint(operationId: 'users.show')]
    public function show(User $user): JsonResponse
    {
        $user = $this->userService->loadProfile($user);

        return (new UserProfileResource($user))->response();
    }

    /**
     * Update user
     *
     * Update the specified user's profile information (name, email, etc.).
     * Password changes are handled separately via PasswordUpdateController.
     * Only the owner can update their data.
     */
    #[Endpoint(operationId: 'users.update')]
    public function update(UserRequest $request, User $user): JsonResponse
    {
        $this->authorize('owner', $user);

        $updatedUser = $this->userService->updateUser($user, $request->toDto());

        return $this->respondUpdated(new UserProfileResource($updatedUser));
    }

    /**
     * Soft delete user.
     *
     * Soft delete the specified user. Only the owner can delete their account. This will also soft delete
     * all projects owned by the user. Tasks, conversations, and messages within those projects are not
     * automatically deleted but become inaccessible when the parent project is abandoned.
     */
    #[Endpoint(operationId: 'users.destroy')]
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('owner', $user);

        $this->userService->deleteUser($user);

        return $this->respondWithMessage('User data deleted successfully.');
    }
}
