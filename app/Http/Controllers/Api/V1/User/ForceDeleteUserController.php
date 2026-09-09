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

        // The route for this controller is registered with `withTrashed()`,
        // so `$user` is already the trashed model. Force delete directly.
        $user->forceDelete();

        return $this->respondWithMessage('User data permanently deleted.');
    }
}
