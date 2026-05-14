<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Auth\LoginUserRequest;
use App\Http\Resources\Api\V1\Auth\AuthenticatedTokenResource;
use App\Services\Auth\LoginUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends ApiController
{
    public function __construct(protected LoginUserService $loginUserService) {}

    /**
     * Authenticate with email and password for bearer-token clients.
     *
     * Returns a Sanctum personal access token for public API clients.
     * Accounts with two-factor authentication enabled must use the session login flow instead.
     *
     * @unauthenticated
     */
    public function login(LoginUserRequest $request): JsonResponse
    {
        $result = $this->loginUserService->startLoginFlow($request->email);

        $user = $result->user;

        if ($result->twoFactor) {
            $this->loginUserService->forgetTwoFactorState();

            abort(
                Response::HTTP_FORBIDDEN,
                'API token login is not available for accounts with two-factor authentication enabled. Use the session login flow.',
            );
        }

        $payload = $this->loginUserService->performApiLogin($user);
        /** @var string $accessToken */
        $accessToken = $payload->accessToken;

        return $this->respondWithData(new AuthenticatedTokenResource($payload->user, $accessToken));
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
