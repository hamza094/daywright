<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Models\Meeting;
use Throwable;

final readonly class ZoomWebhookSupport
{
    public function __construct(
        public ZoomWebhookLogger $logger,
    ) {}

    /**
     * @template T
     *
     * @param  callable(Meeting, ?string): T  $callback
     * @return T
     */
    public function executeWithLogging(string $operation, int|string $meetingId, ?string $requestId, callable $callback): mixed
    {
        $meeting = null;

        try {
            $meeting = $this->getMeeting($meetingId);

            if (! $meeting instanceof Meeting) {
                $this->logger->logWebhookIgnored($operation, $meetingId, $requestId, 'meeting_missing');

                return null;
            }

            return $callback($meeting, $this->userUuid($meeting));
        } catch (Throwable $exception) {
            $userUuid = $meeting instanceof Meeting ? $this->userUuid($meeting) : null;
            $this->logger->logWebhookRetryScheduled($operation, $meetingId, $requestId, $exception, $userUuid);
            throw $exception;
        }
    }

    public function getMeeting(int|string $meetingId): ?Meeting
    {
        return Meeting::where('meeting_id', $meetingId)->first();
    }

    public function lockMeeting(Meeting $meeting): Meeting
    {
        return Meeting::query()
            ->whereKey($meeting->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Provider events are ordered by their provider timestamp. A successful
     * local operation also fences callbacks that predate its completion.
     * Delete handlers may opt into equal timestamps so a distinct delete wins
     * an update/delete tie; inbox event keys still handle true duplicates.
     */
    public function isStaleProviderEvent(
        Meeting $meeting,
        ?int $occurredAt,
        bool $allowEqualTimestamp = false,
    ): bool {
        // Provider timestamps are stored in milliseconds. Webhook processing
        // time must never participate in ordering.
        if ($occurredAt === null) {
            return true;
        }

        if ($meeting->last_zoom_event_timestamp !== null
            && ($allowEqualTimestamp
                ? $occurredAt < $meeting->last_zoom_event_timestamp
                : $occurredAt <= $meeting->last_zoom_event_timestamp)) {
            return true;
        }

        if ($meeting->sync_local_mutation_at === null) {
            return false;
        }

        // This timestamp advances only after a local Zoom mutation is
        // confirmed. Failed attempts and webhook processing never move it.
        return $allowEqualTimestamp
            ? $occurredAt < (int) $meeting->sync_local_mutation_at->valueOf()
            : $occurredAt <= (int) $meeting->sync_local_mutation_at->valueOf();
    }

    public function predatesPendingOperation(Meeting $meeting, ?int $occurredAt): bool
    {
        if ($occurredAt === null || $meeting->sync_started_at === null) {
            return false;
        }

        // Timestamp ties are ambiguous at Zoom's second-level precision, so
        // recovery must reconcile them instead of treating value equality as
        // proof that this callback belongs to the pending operation.
        return $occurredAt <= (int) $meeting->sync_started_at->valueOf();
    }

    public function userUuid(Meeting $meeting): ?string
    {
        return $meeting->user()->value('uuid') ?: null;
    }

    public function ensureActiveSyncStatus(string $operation, Meeting $meeting, int|string $meetingId, ?string $requestId, ?string $userUuid): bool
    {
        if (! $meeting->sync_status->acceptsZoomRuntimeWebhook()) {
            $this->logger->logWebhookIgnored($operation, $meetingId, $requestId, 'inactive_sync_status', $userUuid);

            return false;
        }

        return true;
    }
}
