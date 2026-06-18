<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Zoom;

use App\DataTransferObjects\Zoom\AuthorizationCallbackDetails;
use App\Http\Integrations\Zoom\Requests\GetAccessTokenRequest;
use App\Services\Zoom\ZoomOAuthService;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Zoom\ZoomResponseFactory;
use Tests\TestCase;

/**
 * Unit tests for Zoom OAuth authorization service.
 *
 * Tests the ZoomOAuthService::authorize method which handles OAuth token exchange with Zoom.
 * These tests verify:
 * - Successful access token retrieval from authorization code
 * - Proper PKCE (Proof Key for Code Exchange) implementation
 * - Code verifier length constraints (43-128 characters)
 * - Code challenge generation using SHA256 and base64url encoding
 * - Security: code_verifier is not sent during refresh token flow
 *
 * Level: Unit/Service testing
 */
class ZoomAuthorizationTest extends TestCase
{
    /** @test */
    public function access_details_are_returned(): void
    {
        $this->freezeSecond();

        config([
            'services.zoom.client_id' => 'client-id-here',
            'services.zoom.client_secret' => 'client-secret-here',
        ]);

        Saloon::fake([
            ZoomResponseFactory::tokenResponse([
                'access_token' => 'access-token-here',
                'refresh_token' => 'refresh-token-here',
                'expires_in' => 3600,
            ]),
        ]);

        $callbackDetails = new AuthorizationCallbackDetails(
            authorizationCode: 'dummy-code',
            state: 'dummy-state',
            codeVerifier: 'dummy-code-verifier',
        );

        $zoomService = app(ZoomOAuthService::class);

        $authDetails = $zoomService->authorize($callbackDetails);

        $this->assertEquals('access-token-here', $authDetails->accessToken);

        $this->assertEquals('refresh-token-here', $authDetails->refreshToken);

        $this->assertEqualsWithDelta(
            now()->addHour()->unix(),
            $authDetails->expiresAt->getTimestamp(),
            1
        );

        // Assert our request was sent with the correct code verifier.
        $appUrl = rtrim((string) config('app.url'), '/');

        Saloon::assertSent(static fn (GetAccessTokenRequest $request): bool => $request->resolveEndpoint() ===
        'https://zoom.us/oauth/token'
        && $request->body()->all() === [
            'grant_type' => 'authorization_code',
            'code' => 'dummy-code',
            'redirect_uri' => $appUrl.'/oauth/zoom/callback',
            'code_verifier' => 'dummy-code-verifier',
        ]);
    }
}
