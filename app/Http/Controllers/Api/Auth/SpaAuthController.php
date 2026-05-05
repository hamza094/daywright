<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Auth\LoginUserRequest;
use App\Services\Auth\LoginUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SpaAuthController extends ApiController
{
    public function __construct(protected LoginUserService $loginUserService) {}

    /**
     * @unauthenticated
     * SPA session login (cookie-based via Sanctum stateful).
     *
     * Establishes a session for first-party SPA clients.
     */
    public function loginSpa(LoginUserRequest $request): JsonResponse
    {
        $result = $this->loginUserService->startLoginFlow($request->email);

        $user = $result->user;

        if (($response = $this->loginUserService->twoFactorStateResponse($result)) instanceof JsonResponse) {
            return $response;
        }

        $payload = $this->loginUserService->performSessionLogin($user, $request);

        return $this->respondWithData($payload->toArray());
    }

    /**
     * SPA session logout (cookie-based via Sanctum stateful).
     *
     * Destroys the current session and regenerates CSRF token.
     */
    public function logoutSpa(Request $request): JsonResponse
    {
        // Log out of the session (web guard)
        Auth::guard('web')->logout();

        // Invalidate and regenerate session + CSRF token
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->respondWithMessage('User logout successfully');
    }
}
