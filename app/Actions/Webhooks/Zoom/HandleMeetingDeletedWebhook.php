<?php

declare(strict_types=1);

namespace App\Actions\Webhooks\Zoom;

use App\DataTransferObjects\Zoom\MeetingDeletedWebhookData;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Models\Meeting;
use App\Services\Webhooks\ZoomWebhookSupport;

final readonly class HandleMeetingDeletedWebhook
{
    private const string OPERATION = 'zoom.webhook.meeting.deleted';

    public function __construct(
        private ZoomWebhookSupport $support,
    ) {}

    public function handle(MeetingDeletedWebhookData $data): void
    {
        $this->support->executeWithLogging(self::OPERATION, $data->meetingId, $data->requestId, function (Meeting $meeting, ?string $userUuid) use ($data): void {
            // If already deleting/deleted, treat as already handled
            if (in_array($meeting->sync_status, [MeetingSyncStatus::Deleting, MeetingSyncStatus::Deleted], true)) {
                $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'already_deleted', $userUuid);

                return;
            }

            $meeting->update([
                'sync_status' => MeetingSyncStatus::Deleted,
                'sync_error' => null,
                'synced_at' => now(),
            ]);

            $this->support->logger->logWebhookProcessed(self::OPERATION, $data->meetingId, $data->requestId, $userUuid);
        });
    }
}
