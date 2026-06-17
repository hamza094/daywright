<?php

declare(strict_types=1);

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\MeetingLockOperations;
use App\DataTransferObjects\Zoom\Meeting as ZoomMeeting;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Enums\Subscription\PlanLimitType;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use App\Services\Project\MeetingOperationLock;
use App\Services\Project\MeetingSyncErrorFormatter;
use App\Services\Subscription\PlanLimitService;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class CreateProjectMeeting
{
    use MeetingLockOperations;

    public function __construct(
        private PlanLimitService $planLimitService,
        private MeetingOperationLock $locks,
        private MeetingSyncErrorFormatter $errorFormatter,
        private int $transactionRetryAttempts = 5,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(Project $project, User $user, array $validated, Zoom $zoom): Meeting
    {
        return $this->locks->block(
            key: $this->meetingCreationLockKey($user),
            conflictMessage: 'A meeting is already being created for this user. Please retry.',
            callback: function () use ($project, $user, $validated, $zoom): Meeting {
                $lockedUser = $this->assertCanCreateMeeting($user);
                $projectMeeting = $this->createPendingMeeting($project, $lockedUser, $validated);
                $this->syncWithZoom($projectMeeting, $validated, $lockedUser, $zoom);

                return $projectMeeting->refresh();
            },
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createPendingMeeting(Project $project, User $user, array $validated): Meeting
    {
        return DB::transaction(
            fn (): Meeting => $project->meetings()->create([
                ...$validated,
                'user_id' => $user->id,
                'sync_status' => MeetingSyncStatus::Pending,
            ]),
            attempts: $this->transactionRetryAttempts,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncWithZoom(Meeting $meeting, array $validated, User $user, Zoom $zoom): void
    {
        try {
            $zoomMeeting = $zoom->createMeeting($validated, $user);
            $this->markMeetingAsSynced($meeting, $zoomMeeting);
        } catch (Throwable $exception) {
            $this->markMeetingAsFailed($meeting, $exception);
            throw $exception;
        }
    }

    private function markMeetingAsSynced(Meeting $meeting, ZoomMeeting $zoomMeeting): void
    {
        DB::transaction(function () use ($meeting, $zoomMeeting): void {
            $this->updateMeetingWithLock($meeting, [
                'meeting_id' => $zoomMeeting->meeting_id,
                'start_url' => $zoomMeeting->start_url,
                'join_url' => $zoomMeeting->join_url,
                'status' => $zoomMeeting->status,
                'sync_status' => MeetingSyncStatus::Active,
                'sync_error' => null,
                'synced_at' => now(),
            ]);
        }, attempts: $this->transactionRetryAttempts);
    }

    private function markMeetingAsFailed(Meeting $meeting, Throwable $exception): void
    {
        DB::transaction(function () use ($meeting, $exception): void {
            $this->updateMeetingWithLock($meeting, [
                'sync_status' => MeetingSyncStatus::Failed,
                'sync_error' => $this->errorFormatter->format($exception),
                'sync_attempts' => DB::raw('sync_attempts + 1'),
            ]);
        }, attempts: $this->transactionRetryAttempts);
    }

    private function assertCanCreateMeeting(User $user): User
    {
        return $this->planLimitService->executeWithinAccountLimit(
            PlanLimitType::CreatedMeetings,
            $user,
            fn (User $lockedUser): User => $lockedUser,
        );
    }

    private function meetingCreationLockKey(User $user): string
    {
        return "meeting-create:user:{$user->getKey()}";
    }
}
