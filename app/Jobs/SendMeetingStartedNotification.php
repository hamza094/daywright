<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DataTransferObjects\Meeting\MeetingNotificationData;
use App\Models\Meeting;
use App\Notifications\Zoom\MeetingStarted;

final class SendMeetingStartedNotification extends SendMeetingNotificationJob
{
    protected function uniqueIdPrefix(): string
    {
        return 'meeting-started-notification';
    }

    protected function createNotification(MeetingNotificationData $data): MeetingStarted
    {
        return new MeetingStarted($data);
    }

    protected function hasAlreadySentNotification(Meeting $meeting): bool
    {
        return $meeting->started_notification_sent_at !== null;
    }

    protected function markNotificationAsSent(Meeting $meeting): void
    {
        Meeting::query()
            ->where('id', $this->meetingId)
            ->update(['started_notification_sent_at' => now()]);
    }

    protected function failedLogMessage(): string
    {
        return 'Meeting started notification job failed';
    }
}
