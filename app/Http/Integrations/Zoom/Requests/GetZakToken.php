<?php

declare(strict_types=1);

namespace App\Http\Integrations\Zoom\Requests;

use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetZakToken extends Request
{
    /**
     * The HTTP method of the request
     */
    protected Method $method = Method::GET;

    #[Override]
    public function defaultHeaders(): array
    {
        return [
            'Scopes' => 'user:read:token',
        ];
    }

    /**
     * The endpoint for the request
     */
    #[Override]
    public function resolveEndpoint(): string
    {
        return 'users/me/token?type=zak';
    }
}
