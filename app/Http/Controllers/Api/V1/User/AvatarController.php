<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\User\UserAvatarStoreRequest;
use App\Models\User;
use App\Services\User\AvatarService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AvatarController extends ApiController
{
    /**
     * Uploads and updates the user's avatar.
     *
     * Stores a new avatar image for the targeted user and returns the stored avatar path.
     */
    #[Endpoint(operationId: 'users.uploadAvatar')]
    #[ScrambleResponse(
        status: 200,
        description: 'Avatar stored successfully and linked user profile path returned.',
        type: 'array{data: array{avatar: string, path: string}}',
    )]
    public function store(User $user, UserAvatarStoreRequest $request, AvatarService $service): JsonResponse
    {
        $this->authorize('owner', $user);

        $service->update($user, $request->toDto()->avatar);

        return $this->respondWithData([
            'avatar' => (string) $user->avatar_path,
            'path' => route('api.v1.users.show', ['user' => $user], false),
        ]);
    }

    /**
     * Removes the user's avatar and returns a JSON response.
     *
     * Deletes the stored avatar for the targeted user.
     */
    #[Endpoint(operationId: 'users.removeAvatar')]
    public function destroy(User $user, AvatarService $service): JsonResponse
    {
        $this->authorize('owner', $user);

        $removed = $service->remove($user);

        if (! $removed) {
            abort(Response::HTTP_NOT_FOUND, 'User does not have an avatar');
        }

        return $this->respondWithMessage('User avatar removed successfully.');
    }
}
