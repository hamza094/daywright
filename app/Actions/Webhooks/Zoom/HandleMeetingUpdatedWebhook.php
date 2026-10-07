<?php

declare(strict_types=1);

namespace App\Actions\Webhooks\Zoom;

use App\DataTransferObjects\Zoom\Meeting as ZoomMeeting;
use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use App\Enums\Meeting\MeetingSyncOperationType;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Services\Webhooks\ZoomWebhookSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use Throwable;

use function Safe\json_decode;

final readonly class HandleMeetingUpdatedWebhook
{
    private const string OPERATION = 'zoom.webhook.meeting.updated';

    private const array UPDATE_FIELDS = ['topic', 'duration', 'agenda', 'start_time', 'timezone', 'password', 'join_before_host', 'join_url'];

    public function __construct(
        private ZoomWebhookSupport $support,
        private Zoom $zoom,
    ) {}

    public function handle(MeetingUpdatedWebhookData $data, ?int $occurredAt = null): void
    {
        $this->support->executeWithLogging(self::OPERATION, $data->meetingId, $data->requestId, function (Meeting $meeting, ?string $userUuid) use ($data, $occurredAt): void {
            if ($this->support->isStaleProviderEvent($meeting, $occurredAt)) {
                $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'stale_provider_event', $userUuid);

                return;
            }

            $pendingPayload = $this->isPendingUpdate($meeting)
                ? $this->matchingOperationPayload($meeting, $data->changes)
                : null;
            $requiresReconciliation = $this->support->requiresZoomReconciliation($meeting, $occurredAt)
                || $pendingPayload !== null;
            if ($requiresReconciliation && $meeting->meeting_id === null) {
                throw new RuntimeException('Cannot reconcile a Zoom webhook without a Zoom meeting ID.');
            }

            $initialOperationId = $meeting->sync_operation_id;
            $initialReconcileBeforeAt = $meeting->sync_reconcile_before_at?->valueOf();
            $remoteMeeting = $requiresReconciliation
                ? $this->zoom->getMeeting($meeting->meeting_id, $meeting->user)
                : null;

            DB::transaction(function () use ($meeting, $userUuid, $data, $occurredAt, $requiresReconciliation, $remoteMeeting, $initialOperationId, $initialReconcileBeforeAt): void {
                $lockedMeeting = $this->support->lockMeeting($meeting);

                // Provider timestamps order events. A callback matching a
                // pending operation is reconciled with Zoom before completion.
                if ($this->support->isStaleProviderEvent(
                    $lockedMeeting,
                    $occurredAt,
                )) {
                    $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'stale_provider_event', $userUuid);

                    return;
                }

                if ($lockedMeeting->sync_operation_id !== $initialOperationId
                    || $lockedMeeting->sync_reconcile_before_at?->valueOf() !== $initialReconcileBeforeAt) {
                    throw new RuntimeException('Meeting state changed while reconciling the Zoom webhook.');
                }

                if ($this->support->requiresZoomReconciliation($lockedMeeting, $occurredAt) && ! $requiresReconciliation) {
                    throw new RuntimeException('Meeting requires Zoom reconciliation before applying this webhook.');
                }

                if ($this->isPendingUpdate($lockedMeeting)) {
                    $callbackPredatesOperation = $this->support->predatesPendingOperation($lockedMeeting, $occurredAt);
                    $payload = $this->matchingOperationPayload($lockedMeeting, $data->changes);

                    if ($payload === null) {
                        $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'operation_mismatch', $userUuid);

                        return;
                    }

                    if ($requiresReconciliation) {
                        if ($remoteMeeting === null) {
                            $this->markMeetingDeleted($lockedMeeting, $occurredAt);
                            $this->support->logger->logWebhookProcessed(self::OPERATION, $data->meetingId, $data->requestId, $userUuid);

                            return;
                        }

                        if (! $this->payloadMatchesZoom($payload, $remoteMeeting)) {
                            $lockedMeeting->update(['last_zoom_event_timestamp' => $occurredAt]);
                            $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'zoom_state_does_not_match_operation', $userUuid);

                            return;
                        }

                        if ($callbackPredatesOperation) {
                            $lockedMeeting->update($this->changesFromZoom($remoteMeeting) + [
                                'last_zoom_event_timestamp' => $occurredAt,
                                'synced_at' => now(),
                                'sync_reconcile_before_at' => now(),
                            ]);
                            $this->support->logger->logWebhookProcessed(self::OPERATION, $data->meetingId, $data->requestId, $userUuid);

                            return;
                        }

                        $lockedMeeting->update($this->changesFromZoom($remoteMeeting) + [
                            'sync_status' => MeetingSyncStatus::Active,
                            'sync_operation_id' => null,
                            'sync_operation_type' => null,
                            'sync_payload' => null,
                            'sync_error' => null,
                            'sync_claim_token' => null,
                            'sync_lease_expires_at' => null,
                            'sync_available_at' => null,
                            'last_zoom_event_timestamp' => $occurredAt,
                            'synced_at' => now(),
                            'sync_reconcile_before_at' => now(),
                        ]);
                        $this->support->logger->logWebhookProcessed(self::OPERATION, $data->meetingId, $data->requestId, $userUuid);

                        return;
                    }

                    // Merge validated operation fields with additional normalized provider changes from webhook
                    $additionalChanges = $this->getAdditionalWebhookChanges($payload, $data->changes);
                    $mergedFields = array_merge($payload, $additionalChanges);

                    $lockedMeeting->update($mergedFields + [
                        'sync_status' => MeetingSyncStatus::Active,
                        'sync_operation_id' => null,
                        'sync_operation_type' => null,
                        'sync_payload' => null,
                        'sync_error' => null,
                        'sync_claim_token' => null,
                        'sync_lease_expires_at' => null,
                        'sync_available_at' => null,
                        'last_zoom_event_timestamp' => $occurredAt,
                        'synced_at' => now(),
                    ]);
                    $this->support->logger->logWebhookProcessed(self::OPERATION, $data->meetingId, $data->requestId, $userUuid);

                    return;
                }

                if (! $this->support->ensureActiveSyncStatus(self::OPERATION, $lockedMeeting, $data->meetingId, $data->requestId, $userUuid)) {
                    return;
                }

                if ($requiresReconciliation) {
                    if ($remoteMeeting === null) {
                        $this->markMeetingDeleted($lockedMeeting, $occurredAt);
                        $this->support->logger->logWebhookProcessed(self::OPERATION, $data->meetingId, $data->requestId, $userUuid);

                        return;
                    }

                    $lockedMeeting->update($this->changesFromZoom($remoteMeeting) + [
                        'last_zoom_event_timestamp' => $occurredAt,
                        'synced_at' => now(),
                        'sync_reconcile_before_at' => now(),
                    ]);
                    $this->support->logger->logWebhookProcessed(self::OPERATION, $data->meetingId, $data->requestId, $userUuid);

                    return;
                }

                if (! $this->isMeetingUpdated($lockedMeeting, $data->changes)) {
                    $lockedMeeting->update(['last_zoom_event_timestamp' => $occurredAt]);
                    $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'no_changes', $userUuid);

                    return;
                }

                $lockedMeeting->update($data->changes + ['last_zoom_event_timestamp' => $occurredAt]);
                $this->support->logger->logWebhookProcessed(self::OPERATION, $data->meetingId, $data->requestId, $userUuid);
            });
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadMatchesZoom(array $payload, ZoomMeeting $meeting): bool
    {
        foreach ($payload as $field => $expected) {
            if (! in_array($field, self::UPDATE_FIELDS, true)
                || ! $this->sameValue($field, $expected, $meeting->{$field})) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function changesFromZoom(ZoomMeeting $meeting): array
    {
        return [
            'topic' => $meeting->topic,
            'duration' => $meeting->duration,
            'agenda' => $meeting->agenda,
            'start_time' => $meeting->start_time,
            'timezone' => $meeting->timezone,
            'password' => $meeting->password,
            'join_before_host' => $meeting->join_before_host,
            'join_url' => $meeting->join_url,
        ];
    }

    private function markMeetingDeleted(Meeting $meeting, ?int $occurredAt): void
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

    private function isPendingUpdate(Meeting $meeting): bool
    {
        return in_array($meeting->sync_status, [MeetingSyncStatus::Updating, MeetingSyncStatus::UpdateFailed], true)
            && $meeting->sync_operation_type === MeetingSyncOperationType::Update
            && $meeting->sync_operation_id !== null;
    }

    /**
     * @param  array<string, mixed>  $webhookChanges
     * @return array<string, mixed>|null
     */
    private function matchingOperationPayload(Meeting $meeting, array $webhookChanges): ?array
    {
        if (! is_string($meeting->sync_payload) || trim($meeting->sync_payload) === '') {
            return null;
        }

        try {
            $payload = json_decode($meeting->sync_payload, true);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($payload) || $payload === []) {
            return null;
        }

        foreach ($payload as $field => $expectedValue) {
            if (! is_string($field)
                || ! in_array($field, self::UPDATE_FIELDS, true)
                || ! array_key_exists($field, $webhookChanges)
                || ! $this->sameValue($field, $expectedValue, $webhookChanges[$field])) {
                return null;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $operationPayload
     * @param  array<string, mixed>  $webhookChanges
     * @return array<string, mixed>
     */
    private function getAdditionalWebhookChanges(array $operationPayload, array $webhookChanges): array
    {
        $additional = [];

        foreach ($webhookChanges as $field => $value) {
            // Only include fields that are in the allowlist but not in the operation payload
            if (in_array($field, self::UPDATE_FIELDS, true)
                && ! array_key_exists($field, $operationPayload)) {
                $additional[$field] = $value;
            }
        }

        return $additional;
    }

    private function sameValue(string $field, mixed $expected, mixed $actual): bool
    {
        if ($field === 'start_time') {
            $expectedTime = $this->normalizedTime($expected);

            return $expectedTime !== null && $expectedTime === $this->normalizedTime($actual);
        }

        if ($field === 'duration') {
            return is_numeric($expected) && is_numeric($actual) && (int) $expected === (int) $actual;
        }

        if ($field === 'join_before_host') {
            $expectedBoolean = filter_var($expected, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $expectedBoolean !== null
                && $expectedBoolean === filter_var($actual, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return is_string($expected) && is_string($actual) && $expected === $actual;
    }

    private function normalizedTime(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc()->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
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
