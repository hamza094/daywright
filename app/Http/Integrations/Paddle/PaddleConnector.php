<?php

declare(strict_types=1);

namespace App\Http\Integrations\Paddle;

use Override;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Traits\Plugins\AcceptsJson;

class PaddleConnector extends Connector
{
    use AcceptsJson;

    /**
     * The Base URL of the API
     */
    #[Override]
    public function resolveBaseUrl(): string
    {
        return 'https://sandbox-vendors.paddle.com/api/2.0/subscription/';
    }

    /**
     * Default headers for every request
     */
    #[Override]
    protected function defaultHeaders(): array
    {
        return [];
    }

    /**
     * Default HTTP client options
     */
    #[Override]
    protected function defaultConfig(): array
    {
        return [];
    }
}
