<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\UserAvatarStoreRequest;
use App\Models\User;
use App\Services\Api\V1\AvatarService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AvatarController extends ApiController
{
    /**
     * Uploads and updates the user's avatar.
     */
    public function avatar(User $user, UserAvatarStoreRequest $request, AvatarService $service): JsonResponse
    {
        $this->authorize('owner', $user);

        $service->update($user, $request->file('avatar'));

        return response()->json([
            'message' => 'Avatar Updated Successfully',
            'avatar' => $user->avatar_path,
            'path' => $user->path(),
        ], 200);
    }

    /**
     * Removes the user's avatar and returns a JSON response.
     */
    public function removeAvatar(User $user, AvatarService $service): JsonResponse
    {
        $this->authorize('owner', $user);

        $removed = $service->remove($user);

        if (! $removed) {
            return response()->json([
                'message' => 'User does not have an avatar',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => 'User avatar has been removed',
            'path' => $user->path(),
        ], Response::HTTP_OK);

    }
}
