<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\DataTransferObjects\Zoom\AccessTokenDetails;
use App\DataTransferObjects\Zoom\AuthorizationCallbackDetails;
use App\DataTransferObjects\Zoom\AuthorizationRedirectDetails;
use App\Http\Integrations\Zoom\Requests\GetAccessTokenRequest;
use Closure;
use Illuminate\Support\Str;

class ZoomOAuthService
{
    public function __construct(
        protected readonly ?ZoomConnectorManager $connectors,
    ) {}

    public function getAuthRedirectDetails(): AuthorizationRedirectDetails
    {
        $codeVerifier = Str::random(random_int(43, 128));

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
    ): AccessTokenDetails {
        /** @var AccessTokenDetails $tokenDetails */
        $tokenDetails = $this->connectors->connector()->getAccessToken(
            code: $callbackDetails->authorizationCode,
            state: $callbackDetails->state,
            expectedState: $callbackDetails->expectedState,
            requestModifier: $this->codeVerifierRequestModifier($callbackDetails->codeVerifier),
        );

        return new AccessTokenDetails(
            accessToken: $tokenDetails->accessToken,
            refreshToken: $tokenDetails->refreshToken,
            expiresAt: $tokenDetails->expiresAt,
        );
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
