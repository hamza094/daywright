<?php

declare(strict_types=1);

namespace App\Actions\Webhooks\Zoom;

use App\Models\Meeting;
use App\Services\Webhooks\ZoomWebhookLogger;
use Throwable;

abstract class BaseZoomWebhookAction
{
    public function __construct(
        protected readonly ZoomWebhookLogger $logger,
    ) {}

    abstract protected function operation(): string;

    /**
     * @template T
     *
     * @param  callable(Meeting, ?string): T  $callback
     * @return T
     */
    protected function executeWithLogging(int|string $meetingId, ?string $requestId, callable $callback): mixed
    {
        $meeting = null;

        try {
            $meeting = $this->getMeeting($meetingId);

            if (! $meeting instanceof Meeting) {
                $this->logger->logWebhookIgnored($this->operation(), $meetingId, $requestId, 'meeting_missing');

                return null;
            }

            return $callback($meeting, $this->userUuid($meeting));
        } catch (Throwable $exception) {
            $userUuid = $meeting instanceof Meeting ? $this->userUuid($meeting) : null;
            $this->logger->logWebhookRetryScheduled($this->operation(), $meetingId, $requestId, $exception, $userUuid);
            throw $exception;
        }
    }

    protected function getMeeting(int|string $meetingId): ?Meeting
    {
        return Meeting::where('meeting_id', $meetingId)->first();
    }

    protected function userUuid(Meeting $meeting): ?string
    {
        return $meeting->user()->value('uuid') ?: null;
    }

    protected function ensureActiveSyncStatus(Meeting $meeting, int|string $meetingId, ?string $requestId, ?string $userUuid): bool
    {
        if (! $meeting->sync_status->acceptsZoomRuntimeWebhook()) {
            $this->logger->logWebhookIgnored($this->operation(), $meetingId, $requestId, 'inactive_sync_status', $userUuid);

            return false;
        }

        return true;
    }
}
