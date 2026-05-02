<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class ForceDeleteUserController extends ApiController
{
    public function __invoke(User $user): JsonResponse
    {
        $this->authorize('owner', $user);

        $trashedUser = User::withTrashed()->findOrFail($user->id);
        $trashedUser->forceDelete();

        return response()->json([
            'message' => 'User data permanently deleted.',
        ], 200);
    }
}
