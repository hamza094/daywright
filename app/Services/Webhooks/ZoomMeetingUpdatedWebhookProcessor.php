<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\DataTransferObjects\Zoom\Meeting as ZoomMeeting;
use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use App\Enums\Meeting\MeetingSyncOperationType;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Models\Meeting;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class ZoomMeetingUpdatedWebhookProcessor
{
    private const string OPERATION = 'zoom.webhook.meeting.updated';

    public function __construct(
        private ZoomWebhookSupport $support,
        private ZoomMeetingUpdatedWebhookChanges $changes,
    ) {}

    public function hasPendingUpdate(Meeting $meeting): bool
    {
        return in_array($meeting->sync_status, [MeetingSyncStatus::Updating, MeetingSyncStatus::UpdateFailed], true)
            && $meeting->sync_operation_type === MeetingSyncOperationType::Update
            && $meeting->sync_operation_id !== null;
    }

    /**
     * @param  array<string, mixed>  $webhookChanges
     */
    public function pendingUpdateMatchesEvent(Meeting $meeting, array $webhookChanges): bool
    {
        return $this->hasPendingUpdate($meeting)
            && $this->changes->matchingOperationFields($meeting, $webhookChanges) !== null;
    }

    public function process(
        Meeting $meeting,
        MeetingUpdatedWebhookData $data,
        ?int $occurredAt,
        ?string $userUuid,
        MeetingUpdateWebhookContext $context,
    ): void {
        DB::transaction(fn () => $this->processLockedMeeting(
            $meeting,
            $data,
            $occurredAt,
            $userUuid,
            $context,
        ));
    }

    private function processLockedMeeting(
        Meeting $meeting,
        MeetingUpdatedWebhookData $data,
        ?int $occurredAt,
        ?string $userUuid,
        MeetingUpdateWebhookContext $context,
    ): void {
        // The meeting may have changed while we were asking Zoom for its latest state.
        $lockedMeeting = $this->support->lockMeeting($meeting);

        if ($this->support->isStaleProviderEvent($lockedMeeting, $occurredAt, allowEqualTimestamp: true)) {
            $this->logIgnored($data, 'stale_provider_event', $userUuid);

            return;
        }

        // Check that the Zoom response still belongs to the operation we started with.
        $this->checkMeetingDidNotChange($lockedMeeting, $context);

        // If Zoom says the meeting is gone, mark it deleted even if this webhook is incomplete.
        if ($context->isMissingAtZoom()) {
            $this->markMeetingDeleted($lockedMeeting, $occurredAt);
            $this->logProcessed($data, $userUuid);

            return;
        }

        if ($this->hasPendingUpdate($lockedMeeting)) {
            // A matching event can finish this update; an unrelated event cannot.
            $this->processPendingUpdate($lockedMeeting, $data, $occurredAt, $userUuid, $context);

            return;
        }

        if (! $this->support->ensureActiveSyncStatus(
            self::OPERATION,
            $lockedMeeting,
            $data->meetingId,
            $data->requestId,
            $userUuid,
        )) {
            return;
        }

        if ($context->needsZoomCheck) {
            // The event may be close to a local change, so trust Zoom's current values over the event payload.
            $this->saveZoomSnapshot($lockedMeeting, $data, $occurredAt, $userUuid, $context);

            return;
        }

        $this->saveWebhookChanges($lockedMeeting, $data, $occurredAt, $userUuid);
    }

    private function checkMeetingDidNotChange(
        Meeting $meeting,
        MeetingUpdateWebhookContext $context,
    ): void {
        if ($meeting->sync_operation_id !== $context->operationId
            || $meeting->sync_reconcile_before_at?->valueOf() !== $context->reconcileBefore
            || $meeting->last_zoom_event_timestamp !== $context->providerWatermark) {
            throw new RuntimeException('Meeting state changed while reconciling the Zoom webhook.');
        }
    }

    private function processPendingUpdate(
        Meeting $meeting,
        MeetingUpdatedWebhookData $data,
        ?int $occurredAt,
        ?string $userUuid,
        MeetingUpdateWebhookContext $context,
    ): void {
        $payload = $this->changes->matchingOperationFields($meeting, $data->changes);

        if ($payload === null) {
            // This event does not confirm the values of the update we are waiting for.
            $this->logIgnored($data, 'operation_mismatch', $userUuid);

            return;
        }

        if ($context->needsZoomCheck) {
            $this->finishPendingUpdateFromZoom(
                $meeting,
                $data,
                $occurredAt,
                $userUuid,
                $payload,
                $context->zoomMeetingOrFail(),
            );

            return;
        }

        $mergedChanges = array_merge(
            $payload,
            $this->changes->otherAllowedFields($payload, $data->changes),
        );
        // Save the requested fields and any other safe fields sent by Zoom, then finish the operation.
        $meeting->update($mergedChanges + $this->completedUpdateFields($occurredAt));
        $this->logProcessed($data, $userUuid);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function finishPendingUpdateFromZoom(
        Meeting $meeting,
        MeetingUpdatedWebhookData $data,
        ?int $occurredAt,
        ?string $userUuid,
        array $payload,
        ZoomMeeting $remoteMeeting,
    ): void {
        if (! $this->changes->zoomHasRequestedFields($payload, $remoteMeeting)) {
            // Zoom does not show the requested update yet. Keep it pending so recovery can try again.
            $meeting->update(['last_zoom_event_timestamp' => $occurredAt]);
            $this->logIgnored($data, 'zoom_state_does_not_match_operation', $userUuid);

            return;
        }

        $snapshot = $this->changes->fieldsFromZoom($remoteMeeting);

        if ($this->support->predatesPendingOperation($meeting, $occurredAt)) {
            // This event may be from before the current update. Save Zoom's values, but let recovery finish the update.
            $meeting->update($snapshot + [
                'last_zoom_event_timestamp' => $occurredAt,
                'synced_at' => now(),
                'sync_reconcile_before_at' => now(),
            ]);
            $this->logProcessed($data, $userUuid);

            return;
        }

        $meeting->update($snapshot + $this->completedUpdateFields($occurredAt, advanceReconciliationCutoff: true));
        $this->logProcessed($data, $userUuid);
    }

    private function saveZoomSnapshot(
        Meeting $meeting,
        MeetingUpdatedWebhookData $data,
        ?int $occurredAt,
        ?string $userUuid,
        MeetingUpdateWebhookContext $context,
    ): void {
        $meeting->update($this->changes->fieldsFromZoom($context->zoomMeetingOrFail()) + $this->reconciledEventFields($occurredAt));
        $this->logProcessed($data, $userUuid);
    }

    private function saveWebhookChanges(
        Meeting $meeting,
        MeetingUpdatedWebhookData $data,
        ?int $occurredAt,
        ?string $userUuid,
    ): void {
        if (! $this->changes->hasChanges($meeting, $data->changes)) {
            $meeting->update(['last_zoom_event_timestamp' => $occurredAt]);
            $this->logIgnored($data, 'no_changes', $userUuid);

            return;
        }

        $meeting->update($data->changes + ['last_zoom_event_timestamp' => $occurredAt]);
        $this->logProcessed($data, $userUuid);
    }

    /**
     * @return array<string, mixed>
     */
    private function completedUpdateFields(?int $occurredAt, bool $advanceReconciliationCutoff = false): array
    {
        $fields = [
            'sync_status' => MeetingSyncStatus::Active,
            ...$this->clearedOperationFields(),
            'last_zoom_event_timestamp' => $occurredAt,
            'synced_at' => now(),
        ];

        if ($advanceReconciliationCutoff) {
            $fields['sync_reconcile_before_at'] = now();
        }

        return $fields;
    }

    /**
     * @return array<string, null>
     */
    private function clearedOperationFields(): array
    {
        return [
            'sync_operation_id' => null,
            'sync_operation_type' => null,
            'sync_payload' => null,
            'sync_error' => null,
            'sync_claim_token' => null,
            'sync_lease_expires_at' => null,
            'sync_available_at' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reconciledEventFields(?int $occurredAt): array
    {
        return [
            'last_zoom_event_timestamp' => $occurredAt,
            'synced_at' => now(),
            'sync_reconcile_before_at' => now(),
        ];
    }

    private function markMeetingDeleted(Meeting $meeting, ?int $occurredAt): void
    {
        // Clear the pending operation too, so recovery cannot later change a deleted meeting.
        $updates = [
            'sync_status' => MeetingSyncStatus::Deleted,
            ...$this->clearedOperationFields(),
            'synced_at' => now(),
        ];

        if ($occurredAt !== null) {
            $updates['last_zoom_event_timestamp'] = $occurredAt;
        }

        $meeting->update($updates);
    }

    private function logIgnored(MeetingUpdatedWebhookData $data, string $reason, ?string $userUuid): void
    {
        $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, $reason, $userUuid);
    }

    private function logProcessed(MeetingUpdatedWebhookData $data, ?string $userUuid): void
    {
        $this->support->logger->logWebhookProcessed(self::OPERATION, $data->meetingId, $data->requestId, $userUuid);
    }
}
