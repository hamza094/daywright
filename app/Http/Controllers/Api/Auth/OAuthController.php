<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Actions\UpsertOAuthUserAction;
use App\Enums\OAuthProvider;
use App\Exceptions\Integrations\ExternalServiceUnavailableException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Auth\AuthenticatedSessionResource;
use App\Http\Resources\Api\V1\Auth\TwoFactorChallengeResource;
use App\Services\Auth\LoginUserService;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class OAuthController extends ApiController
{
    public function __construct(protected LoginUserService $loginUserService) {}

    /**
     * Get the OAuth redirect URL.
     *
     * Returns the provider authorization URL used to start the browser-based OAuth flow.
     *
     * @unauthenticated
     */
    public function redirect(OAuthProvider $provider): JsonResponse
    {
        if (auth()->check()) {
            abort(Response::HTTP_BAD_REQUEST, 'User is already authenticated.');
        }
        /** @var \Laravel\Socialite\Two\AbstractProvider $socialiteDriver */
        $socialiteDriver = Socialite::driver($provider->driver());

        $url = $socialiteDriver->stateless()->redirect()->getTargetUrl();

        return $this->respondWithData(['redirect_url' => $url]);
    }

    /**
     * Complete the OAuth callback flow.
     *
     * Creates or updates the linked user account and returns either an authenticated session
     * or a two-factor challenge payload for browser clients.
     *
     * @unauthenticated
     */
    #[ScrambleResponse(
        status: 200,
        description: 'Session created successfully or a two-factor challenge is required.',
        type: 'array{data: AuthenticatedSessionResource|\App\Http\Resources\Api\V1\Auth\TwoFactorChallengeResource}',
    )]
    public function callback(OAuthProvider $provider, UpsertOAuthUserAction $action, Request $request): JsonResponse
    {
        try {
            /** @var \Laravel\Socialite\Two\AbstractProvider $socialiteDriver */
            $socialiteDriver = Socialite::driver($provider->driver());

            $oAuthUser = $socialiteDriver->stateless()->user();

            $user = $action->execute($oAuthUser, $provider);

            if ($user->wasRecentlyCreated) {
                event(new Registered($user));
            }

            $result = $this->loginUserService->startLoginFlow($user->email, $request->ip());

            if ($result->twoFactor) {
                return response()->json([
                    'data' => (new TwoFactorChallengeResource)->resolve($request),
                ], Response::HTTP_OK);
            }

            $payload = $this->loginUserService->performSessionLogin($user, $request);

            return $this->respondWithData(new AuthenticatedSessionResource($payload->user));
        } catch (GuzzleException $e) {
            Log::error('OAuth callback failed', [
                'provider' => $provider->value,
                'message' => $e->getMessage(),
            ]);

            throw new ExternalServiceUnavailableException('Error processing user data.', Response::HTTP_INTERNAL_SERVER_ERROR, $e);
        }
    }
}
