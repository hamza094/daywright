<?php

declare(strict_types=1);

namespace App\Actions\Webhooks\Zoom;

use App\DataTransferObjects\Notification\NotificationActorData;
use App\DataTransferObjects\Zoom\MeetingStartedWebhookData;
use App\Enums\MeetingState;
use App\Events\MeetingStatusUpdate;
use App\Jobs\SendMeetingStartedNotification;
use App\Models\Meeting;
use App\Services\Webhooks\ZoomWebhookSupport;

final readonly class HandleMeetingStartedWebhook
{
    private const string OPERATION = 'zoom.webhook.meeting.started';

    public function __construct(
        private ZoomWebhookSupport $support,
    ) {}

    public function handle(MeetingStartedWebhookData $data): void
    {
        $this->support->executeWithLogging(self::OPERATION, $data->meetingId, $data->requestId, function (Meeting $meeting, ?string $userUuid) use ($data): void {
            if (! $this->support->ensureActiveSyncStatus(self::OPERATION, $meeting, $data->meetingId, $data->requestId, $userUuid)) {
                return;
            }

            $transitioned = $this->transitionToStarted($meeting, $data->meetingId, $data->requestId);

            if ($transitioned) {
                $this->dispatchNotificationJob($meeting, $data->startTime);
                $this->support->logger->logWebhookProcessed(self::OPERATION, $data->meetingId, $data->requestId, $userUuid);
            }
        });
    }

    private function transitionToStarted(Meeting $meeting, int|string $meetingId, ?string $requestId): bool
    {
        $updated = Meeting::query()
            ->whereKey($meeting->getKey())
            ->where('status', '!=', MeetingState::START->value)
            ->where('status', '!=', MeetingState::ENDS->value)
            ->update([
                'status' => MeetingState::START->value,
            ]);

        if ($updated === 0) {
            $userUuid = $this->support->userUuid($meeting);
            $this->support->logger->logWebhookIgnored(self::OPERATION, $meetingId, $requestId, 'already_started_or_ended', $userUuid);

            return false;
        }

        $meeting->refresh();

        event(new MeetingStatusUpdate($meeting));

        return true;
    }

    private function dispatchNotificationJob(Meeting $meeting, ?string $startTime): void
    {
        $notificationData = [
            'project_name' => $meeting->project->name,
            'project_slug' => $meeting->project->slug,
            'meeting_topic' => $meeting->topic,
            'meeting_timezone' => $meeting->timezone,
            'meeting_join_url' => $meeting->join_url,
            'start_time' => $startTime,
            'notifier' => NotificationActorData::fromUser($meeting->project->user)->toArray(),
        ];

        SendMeetingStartedNotification::dispatch($meeting->id, $notificationData);
    }
}
