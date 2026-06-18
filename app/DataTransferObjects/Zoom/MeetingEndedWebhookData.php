<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

final readonly class MeetingEndedWebhookData
{
    public function __construct(
        public int|string $meetingId,
        public ?string $startTime,
        public ?string $endTime,
        public ?string $requestId,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayloadObject(array $payload, ?string $requestId): self
    {
        return new self(
            meetingId: $payload['id'] ?? 0,
            startTime: $payload['start_time'] ?? null,
            endTime: $payload['end_time'] ?? null,
            requestId: $requestId,
        );
    }
}
