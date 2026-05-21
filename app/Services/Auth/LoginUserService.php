<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\DataTransferObjects\Auth\AuthPayload;
use App\DataTransferObjects\Auth\LoginResult;
use App\Events\UserLogin;
use App\Http\Resources\Api\V1\Auth\TwoFactorChallengeResource;
use App\Models\User;
use App\Services\TwoFactor\TwoFactorStateManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LoginUserService
{
    public function __construct(protected TwoFactorStateManager $twoFactorStateManager) {}

    /**
     * Start the login flow shared by different controllers.
     */
    public function startLoginFlow(string $email): LoginResult
    {
        $user = User::where('email', $email)->first();
        $twoFactor = $this->initializeTwoFactorState($user);

        $clientPublicIp = $this->detectPublicRequestIp();

        $this->dispatchTimezoneIfNeeded($user, $clientPublicIp);

        return new LoginResult($user, $twoFactor);
    }

    public function dispatchTimezoneIfNeeded(User $user, ?string $ip = null): void
    {
        if ($user->timezone) {
            return;
        }

        UserLogin::dispatch($user, $ip);
    }

    /**
     * Initialize the two-factor state if the user has 2FA enabled.
     *
     * The email is read from the provided user to avoid duplication.
     */
    public function initializeTwoFactorState(User $user): bool
    {
        if (! $user->hasTwoFactorEnabled()) {
            return false;
        }

        $this->twoFactorStateManager->forgetStateFromSession();

        $this->twoFactorStateManager->createState($user, $user->email);

        return true;
    }

    public function forgetTwoFactorState(): void
    {
        $this->twoFactorStateManager->forgetStateFromSession();
    }

    /**
     * Return the standardized two-factor response when required.
     */
    public function twoFactorStateResponse(LoginResult $result): ?JsonResponse
    {
        if ($result->twoFactor) {
            return $this->buildTwoFactorRequiredResponse();
        }

        return null;
    }

    /**
     * Perform the session login flow for SPA clients and return the auth payload.
     */
    public function performSessionLogin(User $user, Request $request): AuthPayload
    {
        Auth::guard('web')->login($user, false);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return $this->buildAuthSuccessPayload($user, null);
    }

    /**
     * Complete the login for the requested and return the payload.
     */
    public function performApiLogin(User $user): AuthPayload
    {
        $token = $this->createApiToken($user);

        return $this->buildAuthSuccessPayload($user, $token);
    }

    /**
     * Create a personal access token for the given user.
     *
     * Centralizes token creation so controllers don't duplicate logic.
     *
     * @param  array<int, string>  $abilities
     */
    public function createApiToken(User $user, ?string $name = null, array $abilities = ['*']): string
    {
        $name ??= 'Api Token for '.$user->email;

        return $user->createToken($name, $abilities, now()->addMonth())->plainTextToken;
    }

    /**
     * Build the standard authentication success payload used by controllers.
     */
    public function buildAuthSuccessPayload(User $user, ?string $token = null): AuthPayload
    {
        return new AuthPayload($user, $token);
    }

    /**
     * Standardized 2FA required response used across controllers.
     */
    public function buildTwoFactorRequiredResponse(): JsonResponse
    {
        return response()->json([
            'data' => (new TwoFactorChallengeResource)->resolve(request()),
        ], Response::HTTP_OK);
    }

    /**
     * Return the request's public IP or null when the IP is private/invalid.
     */
    private function detectPublicRequestIp(): ?string
    {
        $ip = request()->ip();

        if (! $ip) {
            return null;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ? $ip : null;
    }
}
