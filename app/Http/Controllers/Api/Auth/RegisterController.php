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
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

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
     * Register a new user account.
     *
     * Creates a public user account and returns the created user resource.
     * This endpoint does not create a session or issue an access token.
     *
     * @unauthenticated
     */
    public function register(RegisterUserRequest $request, RegisterUserService $registerUserService): JsonResponse
    {
        try {
            $user = $registerUserService->register($request->registerUserData());

            return $this->respondCreated(new AuthenticatedUserResource($user));
        } catch (TransportExceptionInterface $e) {
            Log::error('User registration failed', ['exception' => $e]);

            throw new ExternalServiceUnavailableException('User registration failed.', Response::HTTP_INTERNAL_SERVER_ERROR, $e);
        }
    }
}
