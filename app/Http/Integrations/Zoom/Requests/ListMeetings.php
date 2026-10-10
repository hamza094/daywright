<?php

declare(strict_types=1);

namespace App\Http\Integrations\Zoom\Requests;

use Override;
use Saloon\Enums\Method;
use Saloon\RateLimitPlugin\Limit;

final class ListMeetings extends ZoomRateLimitedRequest
{
    /**
     * The HTTP method of the request
     */
    protected Method $method = Method::GET;

    public function __construct(
        string $limiterKey,
        private readonly ?string $nextPageToken = null,
    ) {
        parent::__construct($limiterKey);
    }

    /**
     * The endpoint for the request
     */
    #[Override]
    public function resolveEndpoint(): string
    {
        return '/users/me/meetings';
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function defaultQuery(): array
    {
        return array_filter([
            'type' => 'scheduled',
            'page_size' => 300,
            'next_page_token' => $this->nextPageToken,
        ]);
    }

    /**
     * @return array<int, Limit>
     */
    #[Override]
    protected function resolveLimits(): array
    {
        return [
            Limit::allow(requests: 4)->everySeconds(seconds: 1),
            Limit::allow(6000)->everyDay(),
        ];
    }
}
