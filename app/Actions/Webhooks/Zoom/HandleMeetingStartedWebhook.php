<?php

declare(strict_types=1);

namespace App\Actions\Webhooks\Zoom;

use App\DataTransferObjects\Notification\NotificationActorData;
use App\DataTransferObjects\Zoom\MeetingStartedWebhookData;
use App\Enums\MeetingState;
use App\Events\MeetingStatusUpdate;
use App\Models\Meeting;
use App\Notifications\Zoom\MeetingStarted;
use Illuminate\Support\Facades\Notification;

final class HandleMeetingStartedWebhook extends BaseZoomWebhookAction
{
    public function handle(MeetingStartedWebhookData $data): void
    {
        $this->executeWithLogging($data->meetingId, $data->requestId, function (Meeting $meeting, ?string $userUuid) use ($data) {
            if (! $this->ensureActiveSyncStatus($meeting, $data->meetingId, $data->requestId, $userUuid)) {
                return;
            }

            if (! $this->transitionToStarted($meeting, $data->meetingId, $data->requestId)) {
                return;
            }

            $this->sendNotifications($meeting, $data->startTime);
            $this->logger->logWebhookProcessed($this->operation(), $data->meetingId, $data->requestId, $userUuid);
        });
    }

    protected function operation(): string
    {
        return 'zoom.webhook.meeting.started';
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
            $userUuid = $this->userUuid($meeting);
            $this->logger->logWebhookIgnored($this->operation(), $meetingId, $requestId, 'already_started_or_ended', $userUuid);

            return false;
        }

        $meeting->refresh();

        event(new MeetingStatusUpdate($meeting));

        return true;
    }

    private function sendNotifications(Meeting $meeting, ?string $startTime): void
    {
        $project = $meeting->project()->with(['asignees', 'user'])->firstOrFail();
        $user = $project->user;
        $members = $project->asignees;

        $notificationData = [
            'project_name' => $project->name,
            'project_slug' => $project->slug,
            'meeting_topic' => $meeting->topic,
            'meeting_timezone' => $meeting->timezone,
            'meeting_join_url' => $meeting->join_url,
            'start_time' => $startTime,
            'notifier' => NotificationActorData::fromUser($user)->toArray(),
        ];

        Notification::send($members, new MeetingStarted($notificationData));
    }
}
