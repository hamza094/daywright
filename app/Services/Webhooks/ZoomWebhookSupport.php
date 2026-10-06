<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Models\Meeting;
use Carbon\CarbonInterface;
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

    public function isStaleProviderEvent(Meeting $meeting, ?int $occurredAt): bool
    {
        // Provider and application timestamps are stored in milliseconds.
        if ($occurredAt === null) {
            return true;
        }

        if ($meeting->last_zoom_event_timestamp !== null
            && $occurredAt <= $meeting->last_zoom_event_timestamp) {
            return true;
        }

        $latestLocalSync = collect([$meeting->sync_started_at, $meeting->synced_at])
            ->filter(fn (mixed $timestamp): bool => $timestamp instanceof CarbonInterface)
            ->max(fn (CarbonInterface $timestamp): int => (int) $timestamp->valueOf());

        return $latestLocalSync !== null && $occurredAt <= $latestLocalSync;
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
