<?php

declare(strict_types=1);

namespace App\Http\Integrations\Zoom;

use App\Exceptions\Integrations\Zoom\NotFoundException;
use App\Exceptions\Integrations\Zoom\UnauthorizedException;
use App\Exceptions\Integrations\Zoom\ZoomExternalFailureException;
use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use App\Http\Integrations\Zoom\Requests\GetAccessTokenRequest;
use App\Http\Integrations\Zoom\Requests\GetRefreshTokenRequest;
use Override;
use Saloon\Helpers\OAuth2\OAuthConfig;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\OAuth2\AuthorizationCodeGrant;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class ZoomConnector extends Connector
{
    use AcceptsJson;
    use AuthorizationCodeGrant;
    use HasTimeout;

    protected int $connectTimeout = 5;

    protected int $requestTimeout = 30;

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
        $status = $response->status();
        $message = $this->sanitizeExceptionMessage($response->body());
        $context = $this->exceptionContext($response);

        return match (true) {
            $status === HttpResponse::HTTP_FORBIDDEN => (new UnauthorizedException(
                message: $message,
                code: $status,
                previous: $senderException,
            ))->withContext($context),
            $status === HttpResponse::HTTP_NOT_FOUND => (new NotFoundException(
                message: $message,
                code: $status,
                previous: $senderException,
            ))->withContext($context),
            $status === HttpResponse::HTTP_TOO_MANY_REQUESTS,
            $status >= HttpResponse::HTTP_INTERNAL_SERVER_ERROR => (new ZoomExternalFailureException(
                message: $message,
                code: $status,
                previous: $senderException,
            ))->withContext($context),
            default => (new ZoomUserErrorException(
                message: $message,
                code: $status,
                previous: $senderException,
            ))->withContext($context),
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
            throw new ZoomExternalFailureException('Zoom OAuth client credentials are not configured.');
        }

        return OAuthConfig::make()
            ->setClientId($clientId)
            ->setClientSecret($clientSecret)
            ->setRedirectUri($redirect)
            ->setAllowBaseUrlOverride()
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

    private function sanitizeExceptionMessage(string $body): string
    {
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return 'Zoom request failed.';
        }

        return match (true) {
            isset($decoded['message']) => 'Zoom request failed.',
            isset($decoded['error']) => 'Zoom request failed.',
            default => 'Zoom request failed.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function exceptionContext(Response $response): array
    {
        $retryAfter = $response->header('Retry-After');

        if (! is_string($retryAfter) || ! is_numeric($retryAfter)) {
            return [];
        }

        return [
            'retry_after_seconds' => (int) $retryAfter,
        ];
    }
}
