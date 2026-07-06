<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Meeting;
use App\Notifications\Zoom\MeetingEnded;

final class SendMeetingEndedNotification extends SendMeetingNotificationJob
{
    protected function uniqueIdPrefix(): string
    {
        return 'meeting-ended-notification';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createNotification(array $data): MeetingEnded
    {
        return new MeetingEnded($data);
    }

    protected function hasAlreadySentNotification(Meeting $meeting): bool
    {
        return $meeting->ended_notification_sent_at !== null;
    }

    protected function markNotificationAsSent(Meeting $meeting): void
    {
        Meeting::query()
            ->where('id', $this->meetingId)
            ->update(['ended_notification_sent_at' => now()]);
    }

    protected function failedLogMessage(): string
    {
        return 'Meeting ended notification job failed';
    }
}
