<?php

declare(strict_types=1);

namespace App\Http\Integrations\Zoom\Requests;

use Override;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Helpers\OAuth2\OAuthConfig;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;
use Saloon\Traits\Plugins\AcceptsJson;

class GetRefreshTokenRequest extends Request implements HasBody
{
    use AcceptsJson;
    use HasFormBody;

    public ?bool $allowBaseUrlOverride = true;

    /**
     * The HTTP method of the request
     */
    protected Method $method = Method::POST;

    public function __construct(
        private OAuthConfig $oauthConfig,
        private string $refreshToken,
    ) {
        $this->withBasicAuth(
            $oauthConfig->getClientId(), $oauthConfig->getClientSecret()
        );
    }

    /**
     * The endpoint for the request
     */
    #[Override]
    public function resolveEndpoint(): string
    {
        return $this->oauthConfig->getTokenEndpoint();
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultBody(): array
    {
        return [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken,
        ];
    }
}
