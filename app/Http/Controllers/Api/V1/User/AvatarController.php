<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\UserAvatarStoreRequest;
use App\Models\User;
use App\Services\User\AvatarService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AvatarController extends ApiController
{
    /**
     * Uploads and updates the user's avatar.
     */
    public function store(User $user, UserAvatarStoreRequest $request, AvatarService $service): JsonResponse
    {
        $this->authorize('owner', $user);

        $service->update($user, $request->file('avatar'));

        return $this->respondWithData([
            'avatar' => $user->avatar_path,
            'path' => $user->path(),
        ]);
    }

    /**
     * Removes the user's avatar and returns a JSON response.
     */
    public function destroy(User $user, AvatarService $service): JsonResponse
    {
        $this->authorize('owner', $user);

        $removed = $service->remove($user);

        if (! $removed) {
            abort(Response::HTTP_NOT_FOUND, 'User does not have an avatar');
        }

        return $this->respondWithMessage('User avatar has been removed');

    }
}
