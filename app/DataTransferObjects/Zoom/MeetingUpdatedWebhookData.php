<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

use Carbon\Carbon;

final class MeetingUpdatedWebhookData
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(public int|string $meetingId, public array $changes, public ?string $requestId) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayloadObject(array $payload, ?string $requestId): self
    {
        $updateData = MeetingWebhookUpdateData::fromPayloadObject($payload);

        // Normalize start_time to UTC format
        $changes = $updateData->changes;
        if (isset($changes['start_time']) && is_string($changes['start_time'])) {
            $changes['start_time'] = Carbon::parse($changes['start_time'])->setTimezone('UTC')->toDateTimeString();
        }

        return new self(
            meetingId: $updateData->meetingId,
            changes: $changes,
            requestId: $requestId,
        );
    }
}
