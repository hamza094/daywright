<?php

declare(strict_types=1);

namespace App\Http\Integrations\Zoom\Requests;

use App\DataTransferObjects\Zoom\Meeting;
use Override;
use Safe\DateTimeImmutable;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Response;
use Saloon\RateLimitPlugin\Limit;
use Saloon\Traits\Body\HasJsonBody;

class CreateMeeting extends ZoomRateLimitedRequest implements HasBody
{
    use HasJsonBody;

    /**
     * The HTTP method of the request
     */
    protected Method $method = Method::POST;

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
        return '/users/me/meetings';
    }

    #[Override]
    public function createDtoFromResponse(Response $response): mixed
    {
        return Meeting::fromResponse($response->json());
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'topic' => $this->validated['topic'],
            'agenda' => $this->validated['agenda'],
            'duration' => $this->validated['duration'],
            'password' => $this->validated['password'],
            'join_before_host' => $this->validated['join_before_host'],
            'start_time' => (new DateTimeImmutable($this->validated['start_time']))->format('Y-m-d\TH:i:s\Z'),
            'timezone' => $this->validated['timezone'],
        ];
    }

    /**
     * @return array<int, Limit>
     */
    #[Override]
    protected function resolveLimits(): array
    {
        return [
            Limit::allow(requests: 2)->everySeconds(seconds: 1),
            Limit::allow(2000)->everyDay(),
        ];
    }
}
