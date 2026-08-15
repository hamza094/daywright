<?php

declare(strict_types=1);

namespace App\Actions\Webhooks\Zoom;

use App\DataTransferObjects\Meeting\MeetingNotificationData;
use App\DataTransferObjects\Notification\NotificationActorData;
use App\DataTransferObjects\Zoom\MeetingEndedWebhookData;
use App\Enums\MeetingState;
use App\Events\MeetingStatusUpdate;
use App\Jobs\SendMeetingEndedNotification;
use App\Models\Meeting;
use App\Services\Webhooks\ZoomWebhookSupport;

final readonly class HandleMeetingEndedWebhook
{
    private const string OPERATION = 'zoom.webhook.meeting.ended';

    public function __construct(
        private ZoomWebhookSupport $support,
    ) {}

    public function handle(MeetingEndedWebhookData $data): void
    {
        $this->support->executeWithLogging(self::OPERATION, $data->meetingId, $data->requestId, function (Meeting $meeting, ?string $userUuid) use ($data): void {
            if (! $this->support->ensureActiveSyncStatus(self::OPERATION, $meeting, $data->meetingId, $data->requestId, $userUuid)) {
                return;
            }

            $transitioned = $this->transitionToEnded($meeting, $data->meetingId, $data->requestId);

            if ($transitioned) {
                $this->dispatchNotificationJob($meeting, $data->startTime, $data->endTime);
                $this->support->logger->logWebhookProcessed(self::OPERATION, $data->meetingId, $data->requestId, $userUuid);
            }
        });
    }

    private function transitionToEnded(Meeting $meeting, int|string $meetingId, ?string $requestId): bool
    {
        $updated = Meeting::query()
            ->whereKey($meeting->getKey())
            ->where('status', '!=', MeetingState::ENDS->value)
            ->update([
                'status' => MeetingState::ENDS->value,
            ]);

        if ($updated === 0) {
            $userUuid = $this->support->userUuid($meeting);
            $this->support->logger->logWebhookIgnored(self::OPERATION, $meetingId, $requestId, 'already_ended', $userUuid);

            return false;
        }

        $meeting->refresh();

        event(new MeetingStatusUpdate($meeting));

        return true;
    }

    private function dispatchNotificationJob(Meeting $meeting, ?string $startTime, ?string $endTime): void
    {
        $notificationData = MeetingNotificationData::fromArray([
            'project_name' => $meeting->project->name,
            'project_slug' => $meeting->project->slug,
            'meeting_topic' => $meeting->topic,
            'meeting_timezone' => $meeting->timezone,
            'meeting_join_url' => null,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'notifier' => NotificationActorData::fromUser($meeting->project->user)->toArray(),
        ]);

        SendMeetingEndedNotification::dispatch($meeting->id, $notificationData);
    }
}
