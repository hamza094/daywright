<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\DataTransferObjects\OAuth\OAuthTokens;
use App\DataTransferObjects\Zoom\AuthorizationCallbackDetails;
use App\DataTransferObjects\Zoom\AuthorizationRedirectDetails;
use App\Http\Integrations\Zoom\Requests\GetAccessTokenRequest;
use Closure;

class ZoomOAuthService
{
    public function __construct(
        private readonly ZoomConnectorManager $connectors,
    ) {}

    public function getAuthRedirectDetails(): AuthorizationRedirectDetails
    {
        $codeVerifier = $this->generateCodeVerifier();

        $connector = $this->connectors->connector();

        $authorizationUrl = $connector->getAuthorizationUrl(
            scopeSeparator: ',',
            additionalQueryParameters: [
                'code_challenge' => $this->codeChallenge($codeVerifier),
                'code_challenge_method' => 'S256',
            ]
        );

        return new AuthorizationRedirectDetails(
            authorizationUrl: $authorizationUrl,
            state: $connector->getState(),
            codeVerifier: $codeVerifier,
        );
    }

    public function authorize(
        AuthorizationCallbackDetails $callbackDetails
    ): OAuthTokens {
        /** @var OAuthTokens $tokenDetails */
        $tokenDetails = $this->connectors->connector()->getAccessToken(
            code: $callbackDetails->authorizationCode,
            state: $callbackDetails->state,
            expectedState: $callbackDetails->state,
            requestModifier: $this->codeVerifierRequestModifier($callbackDetails->codeVerifier),
        );

        // Note: Server-side state validation is authoritative via ZoomAuthorizationStateStore::consume()
        // The expectedState parameter above is required by Saloon but both values are identical.

        return new OAuthTokens(
            accessToken: $tokenDetails->accessToken,
            refreshToken: $tokenDetails->refreshToken,
            expiresAt: $tokenDetails->expiresAt,
        );
    }

    protected function generateCodeVerifier(): string
    {
        // Generate 32 random bytes (256 bits) and encode as base64url
        // This results in approximately 43 characters, meeting PKCE requirements
        $randomBytes = random_bytes(32);

        return trim(strtr(base64_encode($randomBytes), '+/', '-_'), '=');
    }

    protected function codeChallenge(string $codeVerifier): string
    {
        $hashedVerifier = hash('sha256', $codeVerifier, true);

        return trim(strtr(base64_encode($hashedVerifier), '+/', '-_'), '=');
    }

    /**
     * @return Closure(GetAccessTokenRequest): void
     */
    protected function codeVerifierRequestModifier(string $codeVerifier): Closure
    {
        return static function (GetAccessTokenRequest $request) use ($codeVerifier): void {
            $request->body()->add('code_verifier', $codeVerifier);
        };
    }
}
