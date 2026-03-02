<?php

declare(strict_types=1);

namespace App\Http\Integrations\Zoom;

use App\Exceptions\Integrations\Zoom\NotFoundException;
use App\Exceptions\Integrations\Zoom\UnauthorizedException;
use App\Exceptions\Integrations\Zoom\ZoomException;
use App\Http\Integrations\Zoom\Requests\GetAccessTokenRequest;
use App\Http\Integrations\Zoom\Requests\GetRefreshTokenRequest;
use Override;
use Saloon\Helpers\OAuth2\OAuthConfig;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\OAuth2\AuthorizationCodeGrant;
use Saloon\Traits\Plugins\AcceptsJson;
use Throwable;

class ZoomConnector extends Connector
{
    use AcceptsJson;
    use AuthorizationCodeGrant;

    /**
     * The Base URL of the API.
     */
    #[Override]
    public function resolveBaseUrl(): string
    {
        return 'https://api.zoom.us/v2/';
    }

    #[Override]
    public function getRequestException(
        Response $response, ?Throwable $senderException
    ): ?Throwable {
        return match ($response->status()) {
            403 => new UnauthorizedException(
                message: $response->body(),
                code: $response->status(),
                previous: $senderException,
            ),
            404 => new NotFoundException(
                message: $response->body(),
                code: $response->status(),
                previous: $senderException,
            ),
            default => new ZoomException(
                message: $response->body(),
                code: $response->status(),
                previous: $senderException,
            ),
        };
    }

    /**
     * The OAuth2 configuration
     */
    protected function defaultOauthConfig(): OAuthConfig
    {
        $clientId = (string) config('services.zoom.client_id', '');

        $clientSecret = (string) config('services.zoom.client_secret', '');

        // Fallback redirect for testing/local without full services config
        $redirect = (string) config('services.zoom.redirect');

        if ($redirect === '') {
            $appUrl = (string) config('app.url');
            $redirect = rtrim($appUrl, '/').'/oauth/zoom/callback';
        }

        if ($clientId === '' || $clientId === '0' || ($clientSecret === '' || $clientSecret === '0')) {
            throw new ZoomException('Zoom OAuth client credentials are not configured.');
        }

        return OAuthConfig::make()
            ->setClientId($clientId)
            ->setClientSecret($clientSecret)
            ->setRedirectUri($redirect)
            ->setAuthorizeEndpoint('https://zoom.us/oauth/authorize')
            ->setTokenEndpoint('https://zoom.us/oauth/token')
            ->setDefaultScopes(['user:read:zak',
                'user:read:token',
                'meeting:write:meeting', 'meeting:read:list_meetings',
                'meeting:read:meeting',
                'meeting:delete:meeting',
                'meeting:update:meeting']);
    }

    protected function resolveAccessTokenRequest(
        string $code,
        OAuthConfig $oauthConfig
    ): Request {
        return new GetAccessTokenRequest($code, $oauthConfig);
    }

    protected function resolveRefreshTokenRequest(
        OAuthConfig $oauthConfig,
        string $refreshToken
    ): Request {
        return new GetRefreshTokenRequest($oauthConfig, $refreshToken);
    }
}
