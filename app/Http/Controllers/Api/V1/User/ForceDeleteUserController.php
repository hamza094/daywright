<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class ForceDeleteUserController extends ApiController
{
    /**
     * Permanently delete a previously soft-deleted user profile.
     *
     * Irreversibly removes a user account that has already been soft deleted.
     */
    public function __invoke(User $user): JsonResponse
    {
        $this->authorize('owner', $user);

        $trashedUser = User::withTrashed()->findOrFail($user->id);
        $trashedUser->forceDelete();

        return $this->respondWithMessage('User data permanently deleted.');
    }
}
