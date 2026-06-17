<?php

declare(strict_types=1);

namespace App\Actions\Webhooks\Zoom;

use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use App\Models\Meeting;
use App\Services\Webhooks\ZoomWebhookSupport;
use Carbon\Carbon;

final readonly class HandleMeetingUpdatedWebhook
{
    private const string OPERATION = 'zoom.webhook.meeting.updated';

    public function __construct(
        private ZoomWebhookSupport $support,
    ) {}

    public function handle(MeetingUpdatedWebhookData $data): void
    {
        $this->support->executeWithLogging(self::OPERATION, $data->meetingId, $data->requestId, function (Meeting $meeting, ?string $userUuid) use ($data): void {
            if (! $this->support->ensureActiveSyncStatus(self::OPERATION, $meeting, $data->meetingId, $data->requestId, $userUuid)) {
                return;
            }

            if (! $this->isMeetingUpdated($meeting, $data->changes)) {
                $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'no_changes', $userUuid);

                return;
            }

            $meeting->update($data->changes);
            $this->support->logger->logWebhookProcessed(self::OPERATION, $data->meetingId, $data->requestId, $userUuid);
        });
    }

    /**
     * @param  array<string, mixed>  $updateData
     */
    private function isMeetingUpdated(Meeting $meeting, array $updateData): bool
    {
        foreach ($updateData as $key => $value) {
            if ($this->hasChanged($meeting, $key, $value)) {
                return true;
            }
        }

        return false;
    }

    private function hasChanged(Meeting $meeting, string $key, mixed $value): bool
    {
        $current = $meeting->getAttribute($key);

        if ($key === 'start_time') {
            $currentIso = $current ? Carbon::parse($current)->toISOString() : null;
            $valueIso = $value ? Carbon::parse($value)->toISOString() : null;

            $changed = $currentIso !== $valueIso;
        } elseif (is_bool($current) || is_bool($value)) {
            // Normalize boolean/integer comparisons (1 === true, 0 === false)
            $changed = (bool) $value !== (bool) $current;
        } elseif (is_numeric($current) && is_numeric($value)) {
            // Normalize integer/string comparisons for numeric fields
            $changed = (int) $value !== (int) $current;
        } else {
            $changed = $value !== $current;
        }

        return $changed;
    }
}
