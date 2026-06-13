<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

use Throwable;

final class MeetingUpdatedWebhookData
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public int|string $meetingId,
        array $changes,
        public ?string $requestId,
    ) {
        $this->changes = self::normalizeChanges($changes);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayloadObject(array $payload, ?string $requestId): self
    {
        $updateData = MeetingWebhookUpdateData::fromPayloadObject($payload);

        return new self(
            meetingId: $updateData->meetingId,
            changes: $updateData->changes,
            requestId: $requestId,
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private static function normalizeChanges(array $changes): array
    {
        $filtered = self::filterUnsafeFields($changes);

        if (isset($filtered['start_time'])) {
            $filtered['start_time'] = self::normalizeStartTime($filtered['start_time']);
        }

        return $filtered;
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private static function filterUnsafeFields(array $changes): array
    {
        $allowed = [
            'topic',
            'duration',
            'password',
            'start_time',
            'timezone',
            'uuid',
            'join_url',
            'start_url',
            'agenda',
            'join_before_host',
        ];

        return array_intersect_key($changes, array_flip($allowed));
    }

    private static function normalizeStartTime(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->utc()->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }
}
