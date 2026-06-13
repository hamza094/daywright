<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Zoom;

use App\Services\Zoom\ZoomOAuthService;
use ReflectionClass;
use Tests\TestCase;

use function Safe\parse_url;

class ZoomAuthRedirectDetailsTest extends TestCase
{
    /** @test */
    public function auth_redirect_details_can_be_returned(): void
    {
        config([
            'services.zoom.client_id' => 'client-id-here',
        ]);

        $authDetails = app(ZoomOAuthService::class)->getAuthRedirectDetails();

        // Get the query parameters from the authorization URL.
        $queryParameters = [];
        parse_str(
            parse_url(
                $authDetails->authorizationUrl, PHP_URL_QUERY
            ),
            $queryParameters,
        );

        // Assert the authorization URL has the expected query parameters.
        $this->assertCount(7, $queryParameters);

        $this->assertArrayHasKey('code_challenge', $queryParameters);
        $this->assertArrayHasKey('code_challenge_method', $queryParameters);
        $this->assertEquals('S256', $queryParameters['code_challenge_method']);

        // Assert the state and code challenge are both returned.
        $this->assertEquals($queryParameters['state'], $authDetails->state);
        $this->assertNotEmpty($authDetails->codeVerifier);
    }

    /** @test */
    public function code_verifier_length_is_between_43_and_128_characters(): void
    {
        $service = app(ZoomOAuthService::class);

        for ($i = 0; $i < 100; $i++) {
            $details = $service->getAuthRedirectDetails();
            $length = mb_strlen($details->codeVerifier);
            $this->assertGreaterThanOrEqual(43, $length, "Code verifier too short: {$length}");
            $this->assertLessThanOrEqual(128, $length, "Code verifier too long: {$length}");
        }
    }

    /** @test */
    public function code_challenge_is_correct_base64url_sha256_of_verifier(): void
    {
        $service = app(ZoomOAuthService::class);

        $testVerifier = 'test-verifier-string-for-pkce-validation-12345678';
        $expectedChallenge = trim(strtr(base64_encode(hash('sha256', $testVerifier, true)), '+/', '-_'), '=');

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('codeChallenge');
        $method->setAccessible(true);

        $actualChallenge = $method->invoke($service, $testVerifier);

        $this->assertEquals($expectedChallenge, $actualChallenge);
    }

    /** @test */
    public function code_challenge_uses_base64url_encoding(): void
    {
        $service = app(ZoomOAuthService::class);

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('codeChallenge');
        $method->setAccessible(true);

        $challenge = $method->invoke($service, 'test-verifier');

        // Base64URL should not contain +, /, or =
        $this->assertStringNotContainsString('+', $challenge, 'Challenge contains + (should be -)');
        $this->assertStringNotContainsString('/', $challenge, 'Challenge contains / (should be _)');
        $this->assertStringNotContainsString('=', $challenge, 'Challenge contains = (should be removed)');
    }
}
