<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

use InvalidArgumentException;

final readonly class MeetingUpdatedWebhookData
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public int|string $meetingId,
        public array $changes,
        public ?string $requestId,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayloadObject(array $payload, ?string $requestId): self
    {
        $meetingId = $payload['id'] ?? null;
        if (! is_int($meetingId) && ! is_string($meetingId)) {
            throw new InvalidArgumentException('Zoom webhook payload is missing a valid meeting id.');
        }

        $normalizedChanges = MeetingWebhookUpdateData::normalizeChanges($payload);

        return new self(
            meetingId: $meetingId,
            changes: $normalizedChanges,
            requestId: $requestId,
        );
    }
}
