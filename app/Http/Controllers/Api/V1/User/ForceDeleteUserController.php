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

        // The route for this controller is registered with `withTrashed()`,
        // so `$user` is already the trashed model. Force delete directly.
        $user->forceDelete();

        return $this->respondWithMessage('User data permanently deleted.');
    }
}
