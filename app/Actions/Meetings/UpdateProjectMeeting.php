<?php

declare(strict_types=1);

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\MeetingLockOperations;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Project\MeetingOperationLock;
use App\Services\Project\MeetingSyncErrorFormatter;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class UpdateProjectMeeting
{
    use MeetingLockOperations;

    public function __construct(
        private MeetingOperationLock $locks,
        private MeetingSyncErrorFormatter $errorFormatter,
        private int $transactionRetryAttempts = 5,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(Meeting $meeting, User $user, array $validated, Zoom $zoom): Meeting
    {
        return $this->locks->block(
            key: $this->meetingLockKey($meeting),
            conflictMessage: 'This meeting is currently being updated. Please retry.',
            callback: function () use ($meeting, $user, $validated, $zoom): Meeting {
                $currentMeeting = $this->findMeetingOrFail($meeting);

                try {
                    $this->markMeetingAsUpdating($currentMeeting);
                    $this->updateInZoom($currentMeeting, $validated, $user, $zoom);

                    return $this->markMeetingAsUpdated($currentMeeting, $validated);
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
            $this->updateMeetingWithLock($meeting, [
                'sync_status' => MeetingSyncStatus::Updating,
                'sync_error' => null,
            ]);
        }, attempts: $this->transactionRetryAttempts);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function updateInZoom(Meeting $meeting, array $validated, User $user, Zoom $zoom): void
    {
        $zoom->updateMeeting($validated + ['meeting_id' => $meeting->meeting_id], $user);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function markMeetingAsUpdated(Meeting $meeting, array $validated): Meeting
    {
        /** @var Meeting $updatedMeeting */
        $updatedMeeting = DB::transaction(fn (): Meeting => $this->updateMeetingWithLock($meeting, $validated + [
            'sync_status' => MeetingSyncStatus::Active,
            'sync_error' => null,
            'synced_at' => now(),
        ]), attempts: $this->transactionRetryAttempts);

        return $updatedMeeting;
    }

    private function markMeetingAsUpdateFailed(Meeting $meeting, Throwable $exception): void
    {
        DB::transaction(function () use ($meeting, $exception): void {
            $this->updateMeetingWithLock($meeting, [
                'sync_status' => MeetingSyncStatus::UpdateFailed,
                'sync_error' => $this->errorFormatter->format($exception),
                'sync_attempts' => DB::raw('sync_attempts + 1'),
            ]);
        }, attempts: $this->transactionRetryAttempts);
    }
}
