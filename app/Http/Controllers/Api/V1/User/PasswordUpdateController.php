<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\User\PasswordUpdateRequest;
use App\Services\User\UserService;
use Illuminate\Http\JsonResponse;

class PasswordUpdateController extends ApiController
{
    public function __construct(private readonly UserService $userService) {}

    /**
     * Update user password
     *
     * Update the authenticated user's password. This operation is restricted to
     * first-party clients (web sessions and official mobile apps) only.
     */
    public function update(PasswordUpdateRequest $request): JsonResponse
    {
        $this->userService->updatePassword($request->user(), $request->toDto());

        return $this->respondWithMessage('Password updated successfully.');
    }
}
