<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\Exceptions\Integrations\Zoom\UnauthorizedException;
use App\Exceptions\Integrations\Zoom\ZoomExternalFailureException;
use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use App\Http\Integrations\Zoom\ZoomConnector;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Saloon\Contracts\OAuthAuthenticator;
use Saloon\Http\Auth\AccessTokenAuthenticator;

final class ZoomConnectorManager
{
    private const string REFRESH_UNAVAILABLE_MESSAGE = 'Zoom token refresh is temporarily unavailable. Please try again.';

    private const string REFRESH_LOCK_KEY_PREFIX = 'lock:zoom:oauth-refresh:user:';

    private const string USER_NOT_CONNECTED = 'User is not connected to Zoom.';

    private const string USER_RECONNECT_REQUIRED = 'Zoom account connection needs to be re-authorized.';

    private const int REFRESH_LOCK_SECONDS = 15;

    private const int REFRESH_LOCK_WAIT_SECONDS = 5;

    public function connector(): ZoomConnector
    {
        return new ZoomConnector;
    }

    public function forUser(User $user): ZoomConnector
    {
        $accessTokenDetails = $this->authenticator($user);

        if (! $accessTokenDetails->hasExpired()) {
            return $this->authenticatedConnector($accessTokenDetails);
        }

        return $this->refreshedConnector($user);
    }

    private function refreshedConnector(User $user): ZoomConnector
    {
        try {
            return Cache::lock($this->refreshLockKey($user), self::REFRESH_LOCK_SECONDS)
                ->block(self::REFRESH_LOCK_WAIT_SECONDS, fn (): ZoomConnector => $this->refreshLockedConnector($user));
        } catch (LockTimeoutException $exception) {
            throw new ZoomExternalFailureException(self::REFRESH_UNAVAILABLE_MESSAGE, previous: $exception);
        }
    }

    private function refreshLockedConnector(User $user): ZoomConnector
    {
        $freshUser = $this->connectedUser($user);
        $accessTokenDetails = $this->authenticator($freshUser);

        if (! $accessTokenDetails->hasExpired()) {
            return $this->authenticatedConnector($accessTokenDetails);
        }

        return $this->refreshExpiredAuthenticator($freshUser, $accessTokenDetails);
    }

    private function connectedUser(User $user): User
    {
        $freshUser = $user->fresh();

        if (! $freshUser instanceof User || ! $freshUser->isConnectedToZoom()) {
            throw new ZoomUserErrorException(self::USER_NOT_CONNECTED);
        }

        return $freshUser;
    }

    private function authenticatedConnector(AccessTokenAuthenticator $accessTokenDetails): ZoomConnector
    {
        return $this->connector()->authenticate($accessTokenDetails);
    }

    private function refreshExpiredAuthenticator(User $user, AccessTokenAuthenticator $accessTokenDetails): ZoomConnector
    {
        $connector = $this->authenticatedConnector($accessTokenDetails);

        try {
            $refreshedAccessTokenDetails = $connector->refreshAccessToken($accessTokenDetails);
        } catch (UnauthorizedException $exception) {
            $this->clearCredentialsAndFail($user, $exception);
        }

        $this->persistAuthenticator($refreshedAccessTokenDetails, $user);

        return $connector->authenticate($refreshedAccessTokenDetails);
    }

    private function clearCredentialsAndFail(User $user, UnauthorizedException $exception): never
    {
        $user->clearZoomOAuthDetails();

        throw new ZoomUserErrorException(self::USER_RECONNECT_REQUIRED, previous: $exception);
    }

    private function refreshLockKey(User $user): string
    {
        return self::REFRESH_LOCK_KEY_PREFIX.$user->getKey();
    }

    private function authenticator(User $user): AccessTokenAuthenticator
    {
        return new AccessTokenAuthenticator(
            $user->zoom_access_token,
            $user->zoom_refresh_token,
            $user->zoom_expires_at->toDateTimeImmutable(),
        );
    }

    private function persistAuthenticator(AccessTokenAuthenticator|OAuthAuthenticator $accessTokenDetails, User $user): void
    {
        $user->updateZoomOAuthDetails(
            $accessTokenDetails->getAccessToken(),
            $accessTokenDetails->getRefreshToken(),
            $accessTokenDetails->getExpiresAt(),
        );
    }
}
