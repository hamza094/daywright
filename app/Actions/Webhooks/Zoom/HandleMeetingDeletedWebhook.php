<?php

declare(strict_types=1);

namespace App\Actions\Webhooks\Zoom;

use App\DataTransferObjects\Zoom\MeetingDeletedWebhookData;
use App\Enums\Meeting\MeetingSyncOperationType;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Models\Meeting;
use App\Services\Webhooks\ZoomWebhookSupport;
use Illuminate\Support\Facades\DB;

final readonly class HandleMeetingDeletedWebhook
{
    private const string OPERATION = 'zoom.webhook.meeting.deleted';

    public function __construct(
        private ZoomWebhookSupport $support,
    ) {}

    public function handle(MeetingDeletedWebhookData $data, ?int $occurredAt = null): void
    {
        $this->support->executeWithLogging(self::OPERATION, $data->meetingId, $data->requestId, function (Meeting $meeting, ?string $userUuid) use ($data, $occurredAt): void {
            if ($occurredAt === null) {
                $this->support->logger->logWebhookCritical(
                    self::OPERATION,
                    $data->meetingId,
                    $data->requestId,
                    'zoom_webhook_missing_event_timestamp',
                    $userUuid,
                    ['effect' => 'processing_delete_without_staleness_check'],
                );
            }

            DB::transaction(function () use ($meeting, $userUuid, $data, $occurredAt): void {
                $lockedMeeting = $this->support->lockMeeting($meeting);

                if ($lockedMeeting->sync_status === MeetingSyncStatus::Deleted) {
                    $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'already_deleted', $userUuid);

                    return;
                }

                // A distinct delete wins an equal-timestamp tie with an update.
                if ($occurredAt !== null && $this->support->isStaleProviderEvent($lockedMeeting, $occurredAt, allowEqualTimestamp: true)) {
                    $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'stale_provider_event', $userUuid);

                    return;
                }

                $isPendingDelete = in_array($lockedMeeting->sync_status, [MeetingSyncStatus::Deleting, MeetingSyncStatus::DeleteFailed], true)
                    && $lockedMeeting->sync_operation_type === MeetingSyncOperationType::Delete
                    && $lockedMeeting->sync_operation_id !== null;

                $isPendingUpdate = in_array($lockedMeeting->sync_status, [MeetingSyncStatus::Updating, MeetingSyncStatus::UpdateFailed], true)
                    && $lockedMeeting->sync_operation_type === MeetingSyncOperationType::Update
                    && $lockedMeeting->sync_operation_id !== null;

                if (! $isPendingDelete && ! $isPendingUpdate && $lockedMeeting->sync_status !== MeetingSyncStatus::Active) {
                    $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'inactive_sync_status', $userUuid);

                    return;
                }

                $updates = [
                    'sync_status' => MeetingSyncStatus::Deleted,
                    'sync_operation_id' => null,
                    'sync_operation_type' => null,
                    'sync_payload' => null,
                    'sync_error' => null,
                    'sync_claim_token' => null,
                    'sync_lease_expires_at' => null,
                    'sync_available_at' => null,
                    'synced_at' => now(),
                ];

                if ($occurredAt !== null) {
                    $updates['last_zoom_event_timestamp'] = $occurredAt;
                }

                $lockedMeeting->update($updates);

                $this->support->logger->logWebhookProcessed(self::OPERATION, $data->meetingId, $data->requestId, $userUuid);
            });
        });
    }
}
