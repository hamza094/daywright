<?php

declare(strict_types=1);

namespace App\Http\Integrations\Zoom\Requests;

use Override;
use Saloon\Enums\Method;
use Saloon\RateLimitPlugin\Limit;

class DeleteMeeting extends ZoomRateLimitedRequest
{
    /**
     * The HTTP method of the request
     */
    protected Method $method = Method::DELETE;

    public function __construct(
        private readonly int $meetingId,
        string $limiterKey,
    ) {
        parent::__construct($limiterKey);
    }

    /**
     * The endpoint for the request
     */
    #[Override]
    public function resolveEndpoint(): string
    {
        return '/meetings/'.$this->meetingId;
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
