<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\DataTransferObjects\Auth\AuthPayload;
use App\DataTransferObjects\Auth\LoginResult;
use App\Events\UserLogin;
use App\Models\User;
use App\Services\TwoFactor\TwoFactorStateManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginUserService
{
    public function __construct(protected TwoFactorStateManager $twoFactorStateManager) {}

    /**
     * Start the login flow shared by different controllers.
     */
    public function startLoginFlow(string $email, ?string $ip = null): LoginResult
    {
        $user = User::where('email', $email)->first();

        if (! $user instanceof User) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $twoFactor = $this->initializeTwoFactorState($user);

        $clientPublicIp = $this->validatePublicIp($ip);

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
     * Return whether two-factor authentication is required for the login result.
     */
    public function twoFactorStateResponse(LoginResult $result): bool
    {
        return $result->twoFactor;
    }

    /**
     * Perform the session login flow for SPA clients and return the auth payload.
     */
    public function performSessionLogin(User $user, Request $request): AuthPayload
    {
        Auth::guard('web')->login($user, false);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return $this->buildAuthSuccessPayload($user);
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
     * Return the public IP or null when the IP is private/invalid.
     */
    private function validatePublicIp(?string $ip = null): ?string
    {
        if (! $ip) {
            return null;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ? $ip : null;
    }
}
