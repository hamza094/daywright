<?php

declare(strict_types=1);

namespace App\Actions\Webhooks\Zoom;

use App\DataTransferObjects\Zoom\MeetingDeletedWebhookData;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Models\Meeting;

final class HandleMeetingDeletedWebhook extends BaseZoomWebhookAction
{
    public function handle(MeetingDeletedWebhookData $data): void
    {
        $this->executeWithLogging($data->meetingId, $data->requestId, function (Meeting $meeting, ?string $userUuid) use ($data) {
            // If already deleting/deleted, treat as already handled
            if (in_array($meeting->sync_status, [MeetingSyncStatus::Deleting, MeetingSyncStatus::Deleted], true)) {
                $this->logger->logWebhookIgnored($this->operation(), $data->meetingId, $data->requestId, 'already_deleted', $userUuid);

                return;
            }

            $meeting->update([
                'sync_status' => MeetingSyncStatus::Deleted,
                'sync_error' => null,
                'synced_at' => now(),
            ]);

            $this->logger->logWebhookProcessed($this->operation(), $data->meetingId, $data->requestId, $userUuid);
        });
    }

    protected function operation(): string
    {
        return 'zoom.webhook.meeting.deleted';
    }
}
