<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;

final class ForceDeleteUserController extends ApiController
{
    /**
     * Permanently delete a previously soft-deleted user profile.
     *
     * The user must already be soft-deleted before this endpoint can be used. This operation is irreversible
     * and permanently removes the user account along with their notifications. Projects and associated data
     * are not automatically force-deleted and must be cleaned up separately.
     */
    #[Endpoint(operationId: 'users.forceDelete')]
    public function __invoke(User $user): JsonResponse
    {
        $this->authorize('owner', $user);

        // Explicitly check if user is soft-deleted before allowing force deletion.
        // withTrashed() allows binding both active and soft-deleted models, so we must
        // enforce the archive-first contract with explicit state validation.
        if (! $user->trashed()) {
            return $this->respondConflict('User must be soft-deleted before force deletion.');
        }

        $user->forceDelete();

        return $this->respondWithMessage('User data permanently deleted.');
    }
}
