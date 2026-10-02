<?php

declare(strict_types=1);

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\MeetingLockOperations;
use App\DataTransferObjects\Zoom\Meeting as ZoomMeeting;
use App\DataTransferObjects\Zoom\MeetingStoreData;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Enums\Subscription\PlanLimitType;
use App\Exceptions\Integrations\Zoom\ZoomException;
use App\Exceptions\Integrations\Zoom\ZoomMeetingCreationUnknownException;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use App\Services\Project\MeetingOperationLock;
use App\Services\Project\MeetingSyncErrorFormatter;
use App\Services\Subscription\PlanLimitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    public function handle(Project $project, User $user, MeetingStoreData $data, Zoom $zoom): Meeting
    {
        return $this->locks->block(
            key: $this->meetingCreationLockKey($user),
            conflictMessage: 'A meeting is already being created for this user. Please retry.',
            callback: function () use ($project, $user, $data, $zoom): Meeting {
                $lockedUser = $this->assertCanCreateMeeting($user);
                $operationId = Str::uuid()->toString();
                $projectMeeting = $this->createMeetingWithOperation($project, $lockedUser, $data, $operationId);
                $this->syncWithZoom($projectMeeting, $data, $lockedUser, $zoom, $operationId);

                return $projectMeeting->refresh();
            },
        );
    }

    private function createMeetingWithOperation(Project $project, User $user, MeetingStoreData $data, string $operationId): Meeting
    {
        return DB::transaction(
            fn (): Meeting => $project->meetings()->create([
                ...$data->toArray(),
                'user_id' => $user->id,
                'sync_operation_id' => $operationId,
                'sync_status' => MeetingSyncStatus::Creating,
                'sync_started_at' => now(),
                'sync_lease_expires_at' => now()->addMinutes(5),
            ]),
            attempts: $this->transactionRetryAttempts,
        );
    }

    private function syncWithZoom(Meeting $meeting, MeetingStoreData $data, User $user, Zoom $zoom, string $operationId): void
    {
        try {
            $zoomMeeting = $zoom->createMeeting($data->toArray(), $user, $operationId);
            $this->markMeetingAsSynced($meeting, $zoomMeeting);
        } catch (ZoomMeetingCreationUnknownException $exception) {
            $this->markMeetingAsCreateUnknown($meeting, $exception);
        } catch (ZoomException $exception) {
            Log::warning('Zoom API meeting creation rejected', [
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'exception_class' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);

            $this->markMeetingAsFailed($meeting, $exception);

            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw $exception;
        }
    }

    private function markMeetingAsSynced(Meeting $meeting, ZoomMeeting $zoomMeeting): void
    {
        DB::transaction(function () use ($meeting, $zoomMeeting): void {
            $lockedMeeting = $this->lockMeeting($meeting);

            if ($lockedMeeting->meeting_id !== null && (string) $lockedMeeting->meeting_id !== (string) $zoomMeeting->meeting_id) {
                if ($lockedMeeting->sync_status === MeetingSyncStatus::Creating) {
                    $lockedMeeting->transitionTo(MeetingSyncStatus::CreateUnknown, 'sync_status');
                }

                $lockedMeeting->update([
                    'sync_error' => 'remote_meeting_id_conflict',
                    'sync_claim_token' => null,
                    'sync_lease_expires_at' => null,
                    'sync_available_at' => null,
                ]);

                Log::warning('Zoom meeting creation response conflicts with webhook meeting ID', [
                    'meeting_id' => $lockedMeeting->id,
                    'stored_zoom_meeting_id' => $lockedMeeting->meeting_id,
                    'response_zoom_meeting_id' => $zoomMeeting->meeting_id,
                ]);

                return;
            }

            $lockedMeeting->transitionTo(MeetingSyncStatus::Active, 'sync_status');
            $lockedMeeting->update([
                'meeting_id' => $zoomMeeting->meeting_id,
                'start_url' => $zoomMeeting->start_url,
                'join_url' => $zoomMeeting->join_url,
                'status' => $zoomMeeting->status,
                'sync_error' => null,
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => null,
                'synced_at' => now(),
            ]);
        }, attempts: $this->transactionRetryAttempts);
    }

    private function markMeetingAsFailed(Meeting $meeting, Throwable $exception): void
    {
        DB::transaction(function () use ($meeting, $exception): void {
            $lockedMeeting = $this->lockMeeting($meeting);
            $lockedMeeting->transitionTo(MeetingSyncStatus::Failed, 'sync_status');
            $lockedMeeting->update([
                'sync_error' => $this->errorFormatter->format($exception),
                'sync_attempts' => $lockedMeeting->sync_attempts + 1,
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => null,
            ]);
        }, attempts: $this->transactionRetryAttempts);
    }

    private function markMeetingAsCreateUnknown(Meeting $meeting, Throwable $exception): void
    {
        DB::transaction(function () use ($meeting, $exception): void {
            $lockedMeeting = $this->lockMeeting($meeting);
            $lockedMeeting->transitionTo(MeetingSyncStatus::CreateUnknown, 'sync_status');
            $lockedMeeting->update([
                'sync_error' => $this->errorFormatter->format($exception),
                'sync_attempts' => $lockedMeeting->sync_attempts + 1,
                'sync_available_at' => now()->addMinute(),
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
            ]);
        }, attempts: $this->transactionRetryAttempts);

        report($exception);
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
