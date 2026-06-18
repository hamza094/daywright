<?php

declare(strict_types=1);

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\MeetingLockOperations;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Exceptions\Integrations\Zoom\NotFoundException;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Project\MeetingOperationLock;
use App\Services\Project\MeetingSyncErrorFormatter;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class DeleteProjectMeeting
{
    use MeetingLockOperations;

    public function __construct(
        private MeetingOperationLock $locks,
        private MeetingSyncErrorFormatter $errorFormatter,
        private int $transactionRetryAttempts = 5,
    ) {}

    public function handle(Meeting $meeting, User $user, Zoom $zoom): void
    {
        $this->locks->block(
            key: $this->meetingLockKey($meeting),
            conflictMessage: 'This meeting is currently being deleted. Please retry.',
            callback: function () use ($meeting, $user, $zoom): void {
                $currentMeeting = $this->findMeetingOrFail($meeting);

                try {
                    $this->markMeetingAsDeleting($currentMeeting);
                    $this->deleteFromZoom($currentMeeting, $zoom, $user);
                    $this->markMeetingAsDeleted($currentMeeting);
                } catch (Throwable $exception) {
                    $this->markMeetingAsDeleteFailed($currentMeeting, $exception);
                    throw $exception;
                }
            },
        );
    }

    private function markMeetingAsDeleting(Meeting $meeting): void
    {
        DB::transaction(function () use ($meeting): void {
            $this->updateMeetingWithLock($meeting, [
                'sync_status' => MeetingSyncStatus::Deleting,
                'sync_error' => null,
            ]);
        }, attempts: $this->transactionRetryAttempts);
    }

    private function deleteFromZoom(Meeting $meeting, Zoom $zoom, User $user): void
    {
        try {
            $zoom->deleteMeeting($meeting->meeting_id, $user);
        } catch (NotFoundException) {
            // Treat 404 as success for delete - meeting already doesn't exist in Zoom
        }
    }

    private function markMeetingAsDeleted(Meeting $meeting): void
    {
        DB::transaction(function () use ($meeting): void {
            $this->updateMeetingWithLock($meeting, [
                'sync_status' => MeetingSyncStatus::Deleted,
                'sync_error' => null,
                'synced_at' => now(),
            ]);
        }, attempts: $this->transactionRetryAttempts);
    }

    private function markMeetingAsDeleteFailed(Meeting $meeting, Throwable $exception): void
    {
        DB::transaction(function () use ($meeting, $exception): void {
            $this->updateMeetingWithLock($meeting, [
                'sync_status' => MeetingSyncStatus::DeleteFailed,
                'sync_error' => $this->errorFormatter->format($exception),
                'sync_attempts' => DB::raw('sync_attempts + 1'),
            ]);
        }, attempts: $this->transactionRetryAttempts);
    }
}
