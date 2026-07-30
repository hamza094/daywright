<?php

declare(strict_types=1);

namespace App\Actions\Meetings\Concerns;

use App\Models\Meeting;

trait MeetingLockOperations
{
    private function lockMeeting(Meeting $meeting): Meeting
    {
        return Meeting::query()
            ->whereKey($meeting->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function meetingLockKey(Meeting $meeting): string
    {
        return "meeting:{$meeting->getKey()}";
    }

    private function findMeetingOrFail(Meeting $meeting): Meeting
    {
        return Meeting::query()
            ->whereKey($meeting->getKey())
            ->firstOrFail();
    }
}
