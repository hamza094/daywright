<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Auth\LoginUserRequest;
use App\Http\Resources\Api\V1\Auth\AuthenticatedSessionResource;
use App\Http\Resources\Api\V1\Auth\TwoFactorChallengeResource;
use App\Services\Auth\LoginUserService;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SpaAuthController extends ApiController
{
    public function __construct(protected LoginUserService $loginUserService) {}

    /**
     * Sign in a browser session.
     *
     * Establishes a Sanctum-backed session for first-party browser clients.
     * When two-factor authentication is enabled, this flow returns the challenge state instead.
     *
     * @unauthenticated
     */
    #[ScrambleResponse(
        status: 200,
        description: 'Session created successfully or a two-factor challenge is required.',
        type: 'array{data: AuthenticatedSessionResource|\App\Http\Resources\Api\V1\Auth\TwoFactorChallengeResource}',
    )]
    public function loginSpa(LoginUserRequest $request): JsonResponse
    {
        $credentials = $request->toDto();
        $ip = $request->ip();

        $result = $this->loginUserService->startLoginFlow($credentials->email, $ip);

        $user = $result->user;

        if ($this->loginUserService->twoFactorStateResponse($result)) {
            return response()->json([
                'data' => (new TwoFactorChallengeResource)->resolve($request),
            ], Response::HTTP_OK);
        }

        $payload = $this->loginUserService->performSessionLogin($user, $request);

        return $this->respondWithData(new AuthenticatedSessionResource($payload->user));
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

        return $this->respondWithMessage('User logged out successfully.');
    }
}
