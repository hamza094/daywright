<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

use InvalidArgumentException;

final class MeetingUpdatedWebhookData
{
    /**
     * @var array<string, mixed>
     */
    public array $changes;

    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(public int|string $meetingId, array $changes, public ?string $requestId)
    {
        $this->changes = MeetingWebhookUpdateData::normalizeChanges($changes);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayloadObject(array $payload, ?string $requestId): self
    {
        $meetingId = $payload['id'] ?? null;
        if (! is_int($meetingId) && ! is_string($meetingId)) {
            throw new InvalidArgumentException('Zoom webhook payload is missing a valid meeting id.');
        }

        return new self(
            meetingId: $meetingId,
            changes: $payload,
            requestId: $requestId,
        );
    }
}
