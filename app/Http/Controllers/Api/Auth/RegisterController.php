<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\Integrations\ExternalServiceUnavailableException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Auth\RegisterUserRequest;
use App\Http\Resources\Api\V1\User\AuthenticatedUserResource;
use App\Services\Auth\RegisterUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RegisterController extends ApiController
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    /**
     * @unauthenticated
     * Register User
     *
     * Registers a new user and returns the user API resource.
     */
    public function register(RegisterUserRequest $request, RegisterUserService $registerUserService): JsonResponse
    {
        $validated = $request->validated();

        try {
            $user = $registerUserService->register($validated);

            return $this->respondCreated(new AuthenticatedUserResource($user));
        } catch (Throwable $e) {
            Log::error('User registration failed', ['exception' => $e]);

            throw new ExternalServiceUnavailableException('User registration failed.', Response::HTTP_INTERNAL_SERVER_ERROR, $e);
        }
    }
}
