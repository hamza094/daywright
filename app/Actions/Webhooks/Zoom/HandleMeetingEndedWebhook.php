<?php

declare(strict_types=1);

namespace App\Actions\Webhooks\Zoom;

use App\DataTransferObjects\Notification\NotificationActorData;
use App\DataTransferObjects\Zoom\MeetingEndedWebhookData;
use App\Enums\MeetingState;
use App\Events\MeetingStatusUpdate;
use App\Models\Meeting;
use App\Notifications\Zoom\MeetingEnded;
use Illuminate\Support\Facades\Notification;

final class HandleMeetingEndedWebhook extends BaseZoomWebhookAction
{
    public function handle(MeetingEndedWebhookData $data): void
    {
        $this->executeWithLogging($data->meetingId, $data->requestId, function (Meeting $meeting, ?string $userUuid) use ($data): void {
            if (! $this->ensureActiveSyncStatus($meeting, $data->meetingId, $data->requestId, $userUuid)) {
                return;
            }

            if (! $this->transitionToEnded($meeting, $data->meetingId, $data->requestId)) {
                return;
            }

            $this->sendNotifications($meeting, $data->startTime, $data->endTime);
            $this->logger->logWebhookProcessed($this->operation(), $data->meetingId, $data->requestId, $userUuid);
        });
    }

    protected function operation(): string
    {
        return 'zoom.webhook.meeting.ended';
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
            $userUuid = $this->userUuid($meeting);
            $this->logger->logWebhookIgnored($this->operation(), $meetingId, $requestId, 'already_ended', $userUuid);

            return false;
        }

        $meeting->refresh();

        event(new MeetingStatusUpdate($meeting));

        return true;
    }

    private function sendNotifications(Meeting $meeting, ?string $startTime, ?string $endTime): void
    {
        $project = $meeting->project()->with(['asignees', 'user'])->firstOrFail();
        $user = $project->user;
        $members = $project->asignees;

        $notificationData = [
            'project_name' => $project->name,
            'project_slug' => $project->slug,
            'meeting_topic' => $meeting->topic,
            'meeting_timezone' => $meeting->timezone,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'notifier' => NotificationActorData::fromUser($user)->toArray(),
        ];

        Notification::send($members, new MeetingEnded($notificationData));
    }
}
