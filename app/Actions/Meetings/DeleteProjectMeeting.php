<?php

declare(strict_types=1);

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\MeetingLockOperations;
use App\Actions\Meetings\Concerns\MeetingOperationValidation;
use App\Enums\Meeting\MeetingSyncOperationType;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Exceptions\Integrations\Zoom\NotFoundException;
use App\Exceptions\Integrations\Zoom\ZoomException;
use App\Exceptions\Integrations\Zoom\ZoomMeetingOperationUnknownException;
use App\Exceptions\Integrations\Zoom\ZoomRateLimitException;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Project\MeetingOperationLock;
use App\Services\Project\MeetingSyncErrorFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class DeleteProjectMeeting
{
    use MeetingLockOperations, MeetingOperationValidation;

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
                $operationId = Str::uuid()->toString();

                $lockedMeeting = $this->saveDeleteIntent($meeting, $operationId);

                try {
                    $this->deleteFromZoom($lockedMeeting, $zoom, $user);

                    $this->markMeetingAsDeleted($lockedMeeting, $operationId);
                } catch (ZoomMeetingOperationUnknownException $exception) {
                    $this->markDeleteUnknownAndScheduleRecovery($lockedMeeting, $operationId, $exception);
                    throw $exception;
                } catch (ZoomRateLimitException $exception) {
                    $this->rollbackToActive($lockedMeeting, $operationId);
                    throw $exception;
                } catch (ZoomException $exception) {
                    $this->markDeleteFailed($lockedMeeting, $operationId, $exception);
                    throw $exception;
                } catch (Throwable $exception) {
                    report($exception);
                    throw $exception;
                }
            },
        );
    }

    private function saveDeleteIntent(Meeting $meeting, string $operationId): Meeting
    {
        return DB::transaction(function () use ($meeting, $operationId): Meeting {
            $lockedMeeting = $this->lockMeeting($meeting);

            if ($lockedMeeting->sync_status !== MeetingSyncStatus::Active
                && $lockedMeeting->sync_status !== MeetingSyncStatus::UpdateFailed
                && $lockedMeeting->sync_status !== MeetingSyncStatus::DeleteFailed) {
                throw new RuntimeException('Meeting must be active, update_failed, or delete_failed to delete');
            }

            $lockedMeeting->update([
                'sync_operation_id' => $operationId,
                'sync_operation_type' => MeetingSyncOperationType::Delete,
                'sync_status' => MeetingSyncStatus::Deleting,
                'sync_started_at' => now(),
                'sync_lease_expires_at' => now()->addMinutes(5),
                'sync_error' => null,
            ]);

            return $lockedMeeting;
        }, attempts: $this->transactionRetryAttempts);
    }

    private function deleteFromZoom(Meeting $meeting, Zoom $zoom, User $user): void
    {
        try {
            $zoom->deleteMeeting($meeting->meeting_id, $user);
        } catch (NotFoundException) {
            // The desired remote state already holds: the meeting is absent.
        }
    }

    private function markMeetingAsDeleted(Meeting $meeting, string $operationId): void
    {
        $this->executeWithOperationIdCheck($meeting, $operationId, 'delete finalize', function (Meeting $lockedMeeting): void {
            $lockedMeeting->update([
                'sync_status' => MeetingSyncStatus::Deleted,
                'sync_operation_id' => null,
                'sync_operation_type' => null,
                'sync_error' => null,
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => null,
                'synced_at' => now(),
            ]);
        });
    }

    private function markDeleteUnknownAndScheduleRecovery(Meeting $meeting, string $operationId, Throwable $exception): void
    {
        $this->executeWithOperationIdCheck($meeting, $operationId, 'delete unknown', function (Meeting $lockedMeeting) use ($exception): void {
            $lockedMeeting->update([
                'sync_status' => MeetingSyncStatus::Deleting,
                'sync_error' => $this->errorFormatter->format($exception),
                'sync_attempts' => DB::raw('sync_attempts + 1'),
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => now()->addMinute(),
            ]);
        });

        report($exception);
    }

    private function rollbackToActive(Meeting $meeting, string $operationId): void
    {
        $this->executeWithOperationIdCheck($meeting, $operationId, 'delete rollback', function (Meeting $lockedMeeting): void {
            $lockedMeeting->update([
                'sync_status' => MeetingSyncStatus::Active,
                'sync_operation_id' => null,
                'sync_operation_type' => null,
                'sync_error' => null,
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => null,
            ]);
        });
    }

    private function markDeleteFailed(Meeting $meeting, string $operationId, ZoomException $exception): void
    {
        $this->executeWithOperationIdCheck($meeting, $operationId, 'delete failure', function (Meeting $lockedMeeting) use ($exception): void {
            $lockedMeeting->update([
                'sync_status' => MeetingSyncStatus::DeleteFailed,
                'sync_error' => $this->errorFormatter->format($exception),
                'sync_attempts' => DB::raw('sync_attempts + 1'),
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => null,
            ]);
        });

        Log::warning('Zoom API meeting deletion rejected', [
            'meeting_id' => $meeting->id,
            'exception_class' => $exception::class,
            'exception_code' => $exception->getCode(),
        ]);
    }
}
