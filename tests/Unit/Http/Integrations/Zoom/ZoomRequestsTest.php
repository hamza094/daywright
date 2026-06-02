<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Integrations\Zoom;

use App\Http\Integrations\Zoom\Requests\CreateMeeting;
use App\Http\Integrations\Zoom\Requests\DeleteMeeting;
use App\Http\Integrations\Zoom\Requests\GetAccessTokenRequest;
use App\Http\Integrations\Zoom\Requests\GetRefreshTokenRequest;
use App\Http\Integrations\Zoom\Requests\GetZakToken;
use App\Http\Integrations\Zoom\Requests\UpdateMeeting;
use App\Services\Zoom\ZoomLimiter;
use Closure;
use PHPUnit\Framework\Attributes\Test;
use Safe\DateTimeImmutable;
use Saloon\Enums\Method;
use Saloon\Helpers\OAuth2\OAuthConfig;
use Tests\TestCase;

class ZoomRequestsTest extends TestCase
{
    #[Test]
    public function create_meeting_request_normalizes_body_and_uses_explicit_limiter_identity(): void
    {
        $request = new CreateMeeting([
            'topic' => 'Demo',
            'agenda' => 'Agenda',
            'duration' => 30,
            'password' => 'secret',
            'join_before_host' => false,
            'start_time' => (new DateTimeImmutable('2024-06-18T18:00:07Z'))->format('Y-m-d\TH:i:s\Z'),
            'timezone' => 'UTC',
        ], 'zoom:user:42');

        $this->assertSame('/users/me/meetings', $request->resolveEndpoint());
        $this->assertSame(Method::POST, $request->getMethod());
        $this->assertSame([
            'topic' => 'Demo',
            'agenda' => 'Agenda',
            'duration' => 30,
            'password' => 'secret',
            'join_before_host' => false,
            'start_time' => '2024-06-18T18:00:07Z',
            'timezone' => 'UTC',
        ], $request->body()->all());
        $this->assertSame('CreateMeeting:'.ZoomLimiter::forUserId(42), $this->limiterPrefix($request));
    }

    #[Test]
    public function update_and_delete_requests_use_explicit_limiter_identity(): void
    {
        $updateRequest = new UpdateMeeting([
            'meeting_id' => 1234,
            'topic' => 'Updated topic',
            'start_time' => '2024-06-18T18:00:07Z',
        ], 'zoom:user:99');

        $deleteRequest = new DeleteMeeting(1234, 'zoom:user:99');

        $this->assertSame('/meetings/1234', $updateRequest->resolveEndpoint());
        $this->assertSame(Method::PATCH, $updateRequest->getMethod());
        $this->assertSame([
            'topic' => 'Updated topic',
            'start_time' => '2024-06-18T18:00:07Z',
        ], $updateRequest->body()->all());
        $this->assertSame('UpdateMeeting:'.ZoomLimiter::forUserId(99), $this->limiterPrefix($updateRequest));

        $this->assertSame('/meetings/1234', $deleteRequest->resolveEndpoint());
        $this->assertSame(Method::DELETE, $deleteRequest->getMethod());
        $this->assertSame('DeleteMeeting:'.ZoomLimiter::forUserId(99), $this->limiterPrefix($deleteRequest));
    }

    #[Test]
    public function oauth_and_token_requests_resolve_expected_endpoints_headers_and_body(): void
    {
        $oauthConfig = OAuthConfig::make()
            ->setClientId('client-id')
            ->setClientSecret('client-secret')
            ->setRedirectUri('https://daywright.test/oauth/zoom/callback')
            ->setTokenEndpoint('https://zoom.us/oauth/token');

        $accessTokenRequest = new GetAccessTokenRequest('auth-code', $oauthConfig);
        $refreshTokenRequest = new GetRefreshTokenRequest($oauthConfig, 'refresh-token');
        $zakTokenRequest = new GetZakToken;

        $this->assertSame('https://zoom.us/oauth/token', $accessTokenRequest->resolveEndpoint());
        $this->assertSame(Method::POST, $accessTokenRequest->getMethod());
        $this->assertSame([
            'grant_type' => 'authorization_code',
            'code' => 'auth-code',
            'redirect_uri' => 'https://daywright.test/oauth/zoom/callback',
        ], $accessTokenRequest->body()->all());

        $this->assertSame('https://zoom.us/oauth/token', $refreshTokenRequest->resolveEndpoint());
        $this->assertSame(Method::POST, $refreshTokenRequest->getMethod());
        $this->assertSame([
            'grant_type' => 'refresh_token',
            'refresh_token' => 'refresh-token',
        ], $refreshTokenRequest->body()->all());

        $this->assertSame('users/me/token?type=zak', $zakTokenRequest->resolveEndpoint());
        $this->assertSame(Method::GET, $zakTokenRequest->getMethod());
        $this->assertSame([
            'Scopes' => 'user:read:token',
        ], $zakTokenRequest->defaultHeaders());
    }

    private function limiterPrefix(object $request): string
    {
        return Closure::bind(fn (): string => $this->getLimiterPrefix(), $request, $request)();
    }
}
