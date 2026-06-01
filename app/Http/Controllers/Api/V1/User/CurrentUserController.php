<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\User\CurrentUserResource;
use App\Services\User\UserService;
use Illuminate\Http\JsonResponse;

final class CurrentUserController extends ApiController
{
    public function __construct(private readonly UserService $userService) {}

    /**
     * Get the currently authenticated user.
     *
     * Returns the full current-user payload used by the public product.
     */
    public function __invoke(): JsonResponse
    {
        $user = $this->userService->loadAuthenticatedUser($this->authenticatedUser());

        return $this->respondWithData(new CurrentUserResource($user));
    }
}
