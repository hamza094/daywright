<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Auth\LoginUserRequest;
use App\Services\Api\V1\Auth\LoginUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginController extends ApiController
{
    public function __construct(protected LoginUserService $loginUserService) {}

    /**
     * @unauthenticated
     * Token-based login (for mobile/3rd-party clients).
     *
     * This method authenticates the user using provided credentials
     * and returns a personal access token upon successful login.
     */
    public function login(LoginUserRequest $request): JsonResponse
    {
        $result = $this->loginUserService->startLoginFlow($request->email);

        $user = $result->user;

        if (($response = $this->loginUserService->twoFactorStateResponse($result)) instanceof JsonResponse) {
            return $response;
        }

        $payload = $this->loginUserService->performApiLogin($user);

        return $this->respondWithData($payload->toArray());
    }

    /**
     * Logout token client.
     *
     * Revokes the currently authenticated personal access token.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken|null $currentToken */
        $currentToken = $request->user()->currentAccessToken();
        if ($currentToken) {
            $currentToken->delete();
        }

        return $this->respondWithMessage('User logout successfully');
    }
}
