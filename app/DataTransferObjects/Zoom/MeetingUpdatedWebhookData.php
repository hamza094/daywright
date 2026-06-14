<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

final class MeetingUpdatedWebhookData
{
    public int|string $meetingId;

    /**
     * @var array<string, mixed>
     */
    public array $changes;

    public ?string $requestId;

    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(int|string $meetingId, array $changes, ?string $requestId)
    {
        $this->meetingId = $meetingId;
        $this->changes = MeetingWebhookUpdateData::normalizeChanges($changes);
        $this->requestId = $requestId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayloadObject(array $payload, ?string $requestId): self
    {
        return new self(
            meetingId: $payload['id'] ?? 0,
            changes: $payload,
            requestId: $requestId,
        );
    }
}
