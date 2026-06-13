<?php

declare(strict_types=1);

namespace App\Actions\Webhooks\Zoom;

use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use App\DataTransferObjects\Zoom\MeetingWebhookUpdateData;
use App\Models\Meeting;
use Carbon\Carbon;

final class HandleMeetingUpdatedWebhook extends BaseZoomWebhookAction
{
    public function handle(MeetingUpdatedWebhookData $data): void
    {
        $this->executeWithLogging($data->meetingId, $data->requestId, function (Meeting $meeting, ?string $userUuid) use ($data): void {
            if (! $this->ensureActiveSyncStatus($meeting, $data->meetingId, $data->requestId, $userUuid)) {
                return;
            }

            $safeChanges = MeetingWebhookUpdateData::normalizeChanges($data->changes);

            if (! $this->isMeetingUpdated($meeting, $safeChanges)) {
                $this->logger->logWebhookIgnored($this->operation(), $data->meetingId, $data->requestId, 'no_changes', $userUuid);

                return;
            }

            $meeting->update($safeChanges);
            $this->logger->logWebhookProcessed($this->operation(), $data->meetingId, $data->requestId, $userUuid);
        });
    }

    protected function operation(): string
    {
        return 'zoom.webhook.meeting.updated';
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

            return $currentIso !== $valueIso;
        }

        // Normalize boolean/integer comparisons (1 === true, 0 === false)
        if (is_bool($current) || is_bool($value)) {
            return (bool) $value !== (bool) $current;
        }

        // Normalize integer/string comparisons for numeric fields
        if (is_numeric($current) && is_numeric($value)) {
            return (int) $value !== (int) $current;
        }

        return $value !== $current;
    }
}
