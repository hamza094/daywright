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
            $this->logMissingTimestamp($data, $occurredAt, $userUuid);
            $this->processDeleteInTransaction($meeting, $data, $occurredAt, $userUuid);
        });
    }

    private function logMissingTimestamp(MeetingDeletedWebhookData $data, ?int $occurredAt, ?string $userUuid): void
    {
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
    }

    private function processDeleteInTransaction(Meeting $meeting, MeetingDeletedWebhookData $data, ?int $occurredAt, ?string $userUuid): void
    {
        DB::transaction(function () use ($meeting, $data, $occurredAt, $userUuid): void {
            $lockedMeeting = $this->support->lockMeeting($meeting);

            if ($this->shouldIgnoreWebhook($lockedMeeting, $occurredAt, $data, $userUuid)) {
                return;
            }

            $this->markMeetingAsDeleted($lockedMeeting, $occurredAt);
            $this->support->logger->logWebhookProcessed(self::OPERATION, $data->meetingId, $data->requestId, $userUuid);
        });
    }

    private function shouldIgnoreWebhook(Meeting $meeting, ?int $occurredAt, MeetingDeletedWebhookData $data, ?string $userUuid): bool
    {
        if ($meeting->sync_status === MeetingSyncStatus::Deleted) {
            $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'already_deleted', $userUuid);

            return true;
        }

        if ($occurredAt !== null && $this->support->isStaleProviderEvent($meeting, $occurredAt, allowEqualTimestamp: true)) {
            $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'stale_provider_event', $userUuid);

            return true;
        }

        if (! $this->isValidSyncStatus($meeting)) {
            $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'inactive_sync_status', $userUuid);

            return true;
        }

        return false;
    }

    private function isValidSyncStatus(Meeting $meeting): bool
    {
        $isPendingDelete = $this->isPendingOperation($meeting, MeetingSyncOperationType::Delete, [MeetingSyncStatus::Deleting, MeetingSyncStatus::DeleteFailed]);
        $isPendingUpdate = $this->isPendingOperation($meeting, MeetingSyncOperationType::Update, [MeetingSyncStatus::Updating, MeetingSyncStatus::UpdateFailed]);

        return $isPendingDelete || $isPendingUpdate || $meeting->sync_status === MeetingSyncStatus::Active;
    }

    /**
     * @param  array<MeetingSyncStatus>  $statuses
     */
    private function isPendingOperation(Meeting $meeting, MeetingSyncOperationType $operationType, array $statuses): bool
    {
        return in_array($meeting->sync_status, $statuses, true)
            && $meeting->sync_operation_type === $operationType
            && $meeting->sync_operation_id !== null;
    }

    private function markMeetingAsDeleted(Meeting $meeting, ?int $occurredAt): void
    {
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

        $meeting->update($updates);
    }
}
