<?php

declare(strict_types=1);

namespace App\Actions\Webhooks\Zoom;

use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Services\Webhooks\MeetingUpdateWebhookContext;
use App\Services\Webhooks\ZoomMeetingUpdatedWebhookProcessor;
use App\Services\Webhooks\ZoomWebhookSupport;
use RuntimeException;

final readonly class HandleMeetingUpdatedWebhook
{
    private const string OPERATION = 'zoom.webhook.meeting.updated';

    public function __construct(
        private ZoomWebhookSupport $support,
        private ZoomMeetingUpdatedWebhookProcessor $processor,
        private Zoom $zoom,
    ) {}

    public function handle(MeetingUpdatedWebhookData $data, ?int $occurredAt = null): void
    {
        $this->support->executeWithLogging(
            self::OPERATION,
            $data->meetingId,
            $data->requestId,
            function (Meeting $meeting, ?string $userUuid) use ($data, $occurredAt): void {
                $context = $this->buildContext($meeting, $data, $occurredAt, $userUuid);

                if ($context === null) {
                    return;
                }

                $this->processor->process(
                    $meeting,
                    $data,
                    $occurredAt,
                    $userUuid,
                    $context,
                );
            },
        );
    }

    /**
     * Read Zoom before opening the database transaction. The processor checks
     * that the meeting did not change while this request was in progress.
     */
    public function buildContext(
        Meeting $meeting,
        MeetingUpdatedWebhookData $data,
        ?int $occurredAt,
        ?string $userUuid,
    ): ?MeetingUpdateWebhookContext {
        if ($this->support->isStaleProviderEvent($meeting, $occurredAt, allowEqualTimestamp: true)) {
            $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'stale_provider_event', $userUuid);

            return null;
        }

        $needsZoomCheck = $this->support->requiresZoomReconciliation($meeting, $occurredAt)
            || $this->processor->pendingUpdateMatchesEvent($meeting, $data->changes);

        if ($needsZoomCheck && $meeting->meeting_id === null) {
            throw new RuntimeException('Cannot reconcile a Zoom webhook without a Zoom meeting ID.');
        }

        // Save the current operation details so we can detect changes made during the Zoom request.
        $operationId = $meeting->sync_operation_id;
        $cutoff = $meeting->sync_reconcile_before_at?->valueOf();
        $providerWatermark = $meeting->last_zoom_event_timestamp;
        $remoteMeeting = $needsZoomCheck
            ? $this->zoom->getMeeting($meeting->meeting_id, $meeting->user)
            : null;

        return new MeetingUpdateWebhookContext(
            needsZoomCheck: $needsZoomCheck,
            zoomMeeting: $remoteMeeting,
            operationId: $operationId,
            reconcileBefore: $cutoff,
            providerWatermark: $providerWatermark,
        );
    }
}
