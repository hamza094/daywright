<?php

declare(strict_types=1);

namespace App\Http\Integrations\Zoom\Requests;

use Override;
use Safe\DateTimeImmutable;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\RateLimitPlugin\Limit;
use Saloon\Traits\Body\HasJsonBody;

class UpdateMeeting extends ZoomRateLimitedRequest implements HasBody
{
    use HasJsonBody;

    /**
     * The HTTP method of the request
     */
    protected Method $method = Method::PATCH;

    /**
     * @param  array<string, mixed>  $validated
     */
    public function __construct(
        private readonly array $validated,
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
        return '/meetings/'.$this->validated['meeting_id'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return array_filter([
            'topic' => $this->validated['topic'] ?? null,
            'agenda' => $this->validated['agenda'] ?? null,
            'duration' => $this->validated['duration'] ?? null,
            'start_time' => isset($this->validated['start_time']) ? (new DateTimeImmutable($this->validated['start_time']))->format('Y-m-d\TH:i:s\Z') : null,
            'password' => $this->validated['password'] ?? null,
            'join_before_host' => $this->validated['join_before_host'] ?? null,
            'timezone' => $this->validated['timezone'] ?? null,
        ], fn ($value): bool => ! is_null($value));
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
