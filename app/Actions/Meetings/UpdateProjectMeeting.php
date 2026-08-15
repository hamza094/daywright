<?php

declare(strict_types=1);

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\MeetingLockOperations;
use App\DataTransferObjects\Zoom\MeetingUpdateData;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Project\MeetingOperationLock;
use App\Services\Project\MeetingSyncErrorFormatter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class UpdateProjectMeeting
{
    use MeetingLockOperations;

    public function __construct(
        private MeetingOperationLock $locks,
        private MeetingSyncErrorFormatter $errorFormatter,
        private int $transactionRetryAttempts = 5,
    ) {}

    public function handle(Meeting $meeting, User $user, MeetingUpdateData $data, Zoom $zoom): Meeting
    {
        return $this->locks->block(
            key: $this->meetingLockKey($meeting),
            conflictMessage: 'This meeting is currently being updated. Please retry.',
            callback: function () use ($meeting, $user, $data, $zoom): Meeting {
                $currentMeeting = $this->findMeetingOrFail($meeting);

                try {
                    $this->markMeetingAsUpdating($currentMeeting);
                    $this->updateInZoom($currentMeeting, $data, $user, $zoom);

                    return $this->markMeetingAsUpdated($currentMeeting, $data);
                } catch (Throwable $exception) {
                    $this->markMeetingAsUpdateFailed($currentMeeting, $exception);
                    throw $exception;
                }
            },
        );
    }

    private function markMeetingAsUpdating(Meeting $meeting): void
    {
        DB::transaction(function () use ($meeting): void {
            $lockedMeeting = $this->lockMeeting($meeting);
            $lockedMeeting->transitionTo(MeetingSyncStatus::Updating, 'sync_status');
            $lockedMeeting->update(['sync_error' => null]);
        }, attempts: $this->transactionRetryAttempts);
    }

    private function updateInZoom(Meeting $meeting, MeetingUpdateData $data, User $user, Zoom $zoom): void
    {
        try {
            $zoom->updateMeeting($data->toArray() + ['meeting_id' => $meeting->meeting_id], $user);
        } catch (Throwable $exception) {
            Log::error('Zoom API meeting update failed', [
                'meeting_id' => $meeting->id,
                'zoom_meeting_id' => $meeting->meeting_id,
                'user_id' => $user->id,
                'exception' => $exception,
            ]);
            throw $exception;
        }
    }

    private function markMeetingAsUpdated(Meeting $meeting, MeetingUpdateData $data): Meeting
    {
        return DB::transaction(function () use ($meeting, $data): Meeting {
            $lockedMeeting = $this->lockMeeting($meeting);
            $lockedMeeting->transitionTo(MeetingSyncStatus::Active, 'sync_status');
            $lockedMeeting->update(Arr::except($data->toArray(), ['sync_status']) + [
                'sync_error' => null,
                'synced_at' => now(),
            ]);

            return $lockedMeeting;
        }, attempts: $this->transactionRetryAttempts);
    }

    private function markMeetingAsUpdateFailed(Meeting $meeting, Throwable $exception): void
    {
        DB::transaction(function () use ($meeting, $exception): void {
            $lockedMeeting = $this->lockMeeting($meeting);
            $lockedMeeting->transitionTo(MeetingSyncStatus::UpdateFailed, 'sync_status');
            $lockedMeeting->update([
                'sync_error' => $this->errorFormatter->format($exception),
                'sync_attempts' => DB::raw('sync_attempts + 1'),
            ]);
        }, attempts: $this->transactionRetryAttempts);
    }
}
