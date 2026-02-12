<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Actions\OAuthAction;
use App\Enums\OAuthProvider;
use App\Http\Controllers\Api\ApiController;
use App\Services\Api\V1\Auth\LoginUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class OAuthController extends ApiController
{
    public function __construct(protected LoginUserService $loginUserService) {}

    /**
     * @unauthenticated
     * Get OAuth redirect url
     */
    public function redirect(OAuthProvider $provider): JsonResponse
    {
        if (auth()->check()) {
            return response()->json(['error' => 'User is already authenticated.'], 400);
        }
        /** @var \Laravel\Socialite\Two\AbstractProvider $socialiteDriver */
        $socialiteDriver = Socialite::driver($provider->driver());

        $url = $socialiteDriver->stateless()->redirect()->getTargetUrl();

        return response()->json(['redirect_url' => $url]);
    }

    /**
     * @unauthenticated
     *  OAuth Authentication
     *
     * API endpoint for creating or updating user and enabling login through OAuth provider callback.
     */
    public function callback(OAuthProvider $provider, OAuthAction $action, Request $request): JsonResponse
    {
        try {
            /** @var \Laravel\Socialite\Two\AbstractProvider $socialiteDriver */
            $socialiteDriver = Socialite::driver($provider->driver());

            $oAuthUser = $socialiteDriver->stateless()->user();

            $user = $action->createUpdateUser($oAuthUser, $provider);

            $result = $this->loginUserService->startLoginFlow($user->email);

            if ($result->twoFactor) {
                return $this->loginUserService->buildTwoFactorRequiredResponse();
            }

            $payload = $this->loginUserService->performSessionLogin($user, $request);

            return response()->json($payload->toArray(), 200);
        } catch (Throwable $e) {
            Log::error('OAuth callback failed', [
                'provider' => $provider->value,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error processing user data.',
            ], 500);
        }

    }
}
