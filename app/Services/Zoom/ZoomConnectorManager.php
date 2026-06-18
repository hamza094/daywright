<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\DataTransferObjects\OAuth\OAuthTokens;
use App\Exceptions\Integrations\Zoom\UnauthorizedException;
use App\Exceptions\Integrations\Zoom\ZoomExternalFailureException;
use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use App\Http\Integrations\Zoom\ZoomConnector;
use App\Models\User;
use App\Repository\OAuthConnectionRepository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Saloon\Http\Auth\AccessTokenAuthenticator;

final readonly class ZoomConnectorManager
{
    private const string PROVIDER = 'zoom';

    private const string USER_NOT_CONNECTED = 'User is not connected to Zoom.';

    private const string USER_RECONNECT_REQUIRED = 'Zoom account connection needs to be re-authorized.';

    private const string REFRESH_UNAVAILABLE_MESSAGE = 'Zoom token refresh is temporarily unavailable. Please try again.';

    private const string REFRESH_LOCK_KEY_PREFIX = 'lock:zoom:oauth-refresh:user:';

    private const int REFRESH_LOCK_SECONDS = 15;

    private const int REFRESH_LOCK_WAIT_SECONDS = 5;

    public function __construct(
        private OAuthConnectionRepository $oauthRepository,
    ) {}

    public function connector(): ZoomConnector
    {
        return new ZoomConnector;
    }

    public function forUser(User $user): ZoomConnector
    {
        $authenticator = $this->authenticatorFor($user);

        if ($authenticator->hasExpired()) {
            $authenticator = $this->refreshAuthenticator($user);
        }

        return $this->connector()->authenticate($authenticator);
    }

    private function refreshAuthenticator(User $user): AccessTokenAuthenticator
    {
        try {
            return Cache::lock($this->refreshLockKey($user), self::REFRESH_LOCK_SECONDS)
                ->block(
                    self::REFRESH_LOCK_WAIT_SECONDS,
                    fn (): AccessTokenAuthenticator => $this->refreshAuthenticatorInsideLock($user),
                );
        } catch (LockTimeoutException $exception) {
            throw new ZoomExternalFailureException(self::REFRESH_UNAVAILABLE_MESSAGE, $exception->getCode(), previous: $exception);
        }
    }

    private function refreshAuthenticatorInsideLock(User $user): AccessTokenAuthenticator
    {
        $authenticator = $this->authenticatorFor($user);

        if (! $authenticator->hasExpired()) {
            return $authenticator;
        }

        return $this->refreshAndSave($user, $authenticator);
    }

    private function refreshAndSave(
        User $user,
        AccessTokenAuthenticator $authenticator,
    ): AccessTokenAuthenticator {
        try {
            $refreshed = $this->connector()
                ->authenticate($authenticator)
                ->refreshAccessToken($authenticator);
        } catch (UnauthorizedException $exception) {
            $this->oauthRepository->clearTokens($user, self::PROVIDER);

            throw new ZoomUserErrorException(self::USER_RECONNECT_REQUIRED, $exception->getCode(), previous: $exception);
        } catch (ZoomUserErrorException $exception) {
            // Check for OAuth error codes that indicate the refresh token is invalid
            if ($this->isInvalidOAuthError($exception)) {
                $this->oauthRepository->clearTokens($user, self::PROVIDER);

                throw new ZoomUserErrorException(self::USER_RECONNECT_REQUIRED, $exception->getCode(), previous: $exception);
            }

            throw $exception;
        }

        $this->oauthRepository->saveTokens(
            $user,
            self::PROVIDER,
            new OAuthTokens(
                accessToken: $refreshed->getAccessToken(),
                refreshToken: $refreshed->getRefreshToken(),
                expiresAt: $refreshed->getExpiresAt(),
            ),
        );

        return new AccessTokenAuthenticator(
            $refreshed->getAccessToken(),
            $refreshed->getRefreshToken(),
            $refreshed->getExpiresAt(),
        );
    }

    private function isInvalidOAuthError(ZoomUserErrorException $exception): bool
    {
        $context = $exception->context();

        if (! isset($context['error']) || ! is_string($context['error'])) {
            return false;
        }

        return in_array($context['error'], ['invalid_grant', 'invalid_token'], true);
    }

    private function authenticatorFor(User $user): AccessTokenAuthenticator
    {
        $tokens = $this->oauthRepository->getTokens($user, self::PROVIDER);

        if (! $tokens instanceof OAuthTokens) {
            throw new ZoomUserErrorException(self::USER_NOT_CONNECTED);
        }

        return new AccessTokenAuthenticator(
            $tokens->accessToken,
            $tokens->refreshToken,
            $tokens->expiresAt,
        );
    }

    private function refreshLockKey(User $user): string
    {
        return self::REFRESH_LOCK_KEY_PREFIX.$user->getKey();
    }
}
