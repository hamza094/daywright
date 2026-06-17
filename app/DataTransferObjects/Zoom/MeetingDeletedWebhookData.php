<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

use InvalidArgumentException;

final readonly class MeetingDeletedWebhookData
{
    public function __construct(
        public int|string $meetingId,
        public ?string $requestId = null,
    ) {}

    /**
     * @param  array<string, int|string|null>  $payload
     */
    public static function fromPayloadObject(array $payload, ?string $requestId): self
    {
        $meetingId = $payload['id'] ?? null;
        if (! is_int($meetingId) && ! is_string($meetingId)) {
            throw new InvalidArgumentException('Zoom webhook payload object.id is required.');
        }

        return new self(
            meetingId: $meetingId,
            requestId: $requestId,
        );
    }
}
