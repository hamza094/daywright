<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\FileType;
use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Services\Api\V1\FileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AvatarController extends ApiController
{
    /**
     * Uploads and updates the user's avatar.
     */
    public function avatar(User $user, Request $request, FileService $service): JsonResponse
    {
        $this->authorize('owner', $user);

        $request->validate([
            'avatar' => [
                'required', 'image', 'max:700', 'mimes:jpeg,png,jpg',
            ],
        ]);

        $user_path = $service->store($user->uuid, $request->file('avatar'), FileType::AVATAR);

        $user->update(['avatar_path' => $user_path]);

        return response()->json([
            'message' => 'Avatar Updated Successfully',
            'avatar' => $user->avatar_path,
            'path' => $user->path(),
        ], 200);
    }

    /**
     * Removes the user's avatar and returns a JSON response.
     */
    public function removeAvatar(User $user, FileService $service): JsonResponse
    {
        $this->authorize('owner', $user);

        if (! $user->avatar) {
            return response()->json([
                'message' => 'User does not have an avatar',
            ], Response::HTTP_NOT_FOUND);
        }

        $service->deleteFile($user);

        return response()->json([
            'message' => 'User avatar has been removed',
            'path' => $user->path(),
        ], Response::HTTP_OK);

    }
}
