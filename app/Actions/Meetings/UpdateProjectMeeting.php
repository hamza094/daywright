<?php

declare(strict_types=1);

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\MeetingLockOperations;
use App\Actions\Meetings\Concerns\MeetingOperationValidation;
use App\DataTransferObjects\Zoom\MeetingUpdateData;
use App\Enums\Meeting\MeetingSyncOperationType;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Exceptions\Integrations\Zoom\ZoomException;
use App\Exceptions\Integrations\Zoom\ZoomMeetingOperationUnknownException;
use App\Exceptions\Integrations\Zoom\ZoomRateLimitException;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Project\MeetingOperationLock;
use App\Services\Project\MeetingSyncErrorFormatter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

use function Safe\json_encode;

final readonly class UpdateProjectMeeting
{
    use MeetingLockOperations, MeetingOperationValidation;

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
                $operationId = Str::uuid()->toString();
                $payload = json_encode($data->toArray());

                $lockedMeeting = $this->saveUpdateIntent($meeting, $operationId, $payload);

                try {
                    $this->updateInZoom($lockedMeeting, $data, $user, $zoom);

                    return $this->applyUpdateLocally($lockedMeeting, $data, $operationId);
                } catch (ZoomMeetingOperationUnknownException $exception) {
                    $this->markUpdateUnknownAndScheduleRecovery($lockedMeeting, $operationId, $exception);
                    throw $exception;
                } catch (ZoomRateLimitException $exception) {
                    $this->rollbackToActive($lockedMeeting, $operationId);
                    throw $exception;
                } catch (ZoomException $exception) {
                    $this->markUpdateFailed($lockedMeeting, $operationId, $exception);
                    throw $exception;
                } catch (Throwable $exception) {
                    report($exception);
                    throw $exception;
                }
            },
        );
    }

    private function saveUpdateIntent(Meeting $meeting, string $operationId, string $payload): Meeting
    {
        return DB::transaction(function () use ($meeting, $operationId, $payload): Meeting {
            $lockedMeeting = $this->lockMeeting($meeting);

            if ($lockedMeeting->sync_status !== MeetingSyncStatus::Active
                && $lockedMeeting->sync_status !== MeetingSyncStatus::UpdateFailed) {
                throw new RuntimeException('Meeting must be active or update_failed to update');
            }

            $lockedMeeting->update([
                'sync_operation_id' => $operationId,
                'sync_operation_type' => MeetingSyncOperationType::Update,
                'sync_payload' => $payload,
                'sync_status' => MeetingSyncStatus::Updating,
                'sync_started_at' => now(),
                'sync_lease_expires_at' => now()->addMinutes(5),
                'sync_attempts' => 0,
                'sync_claim_token' => null,
                'sync_available_at' => null,
                'sync_error' => null,
            ]);

            return $lockedMeeting;
        }, attempts: $this->transactionRetryAttempts);
    }

    private function updateInZoom(Meeting $meeting, MeetingUpdateData $data, User $user, Zoom $zoom): void
    {
        $zoom->updateMeeting($data->toArray() + ['meeting_id' => $meeting->meeting_id], $user);
    }

    private function applyUpdateLocally(Meeting $meeting, MeetingUpdateData $data, string $operationId): Meeting
    {
        return $this->executeWithOperationIdCheck($meeting, $operationId, 'local apply', function (Meeting $lockedMeeting) use ($data): Meeting {
            $lockedMeeting->update(Arr::except($data->toArray(), ['sync_status']) + [
                'sync_status' => MeetingSyncStatus::Active,
                'sync_operation_id' => null,
                'sync_operation_type' => null,
                'sync_payload' => null,
                'sync_error' => null,
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'synced_at' => now(),
            ]);

            return $lockedMeeting;
        });
    }

    private function markUpdateUnknownAndScheduleRecovery(Meeting $meeting, string $operationId, Throwable $exception): void
    {
        $this->executeWithOperationIdCheck($meeting, $operationId, 'update unknown', function (Meeting $lockedMeeting) use ($exception): void {
            $lockedMeeting->update([
                'sync_status' => MeetingSyncStatus::Updating,
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
        $this->executeWithOperationIdCheck($meeting, $operationId, 'rollback', function (Meeting $lockedMeeting): void {
            $lockedMeeting->update([
                'sync_status' => MeetingSyncStatus::Active,
                'sync_operation_id' => null,
                'sync_operation_type' => null,
                'sync_payload' => null,
                'sync_error' => null,
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => null,
            ]);
        });
    }

    private function markUpdateFailed(Meeting $meeting, string $operationId, ZoomException $exception): void
    {
        $this->executeWithOperationIdCheck($meeting, $operationId, 'update failure', function (Meeting $lockedMeeting) use ($exception): void {
            $lockedMeeting->update([
                'sync_status' => MeetingSyncStatus::UpdateFailed,
                'sync_error' => $this->errorFormatter->format($exception),
                'sync_attempts' => DB::raw('sync_attempts + 1'),
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => null,
            ]);
        });

        Log::warning('Zoom API meeting update rejected', [
            'meeting_id' => $meeting->id,
            'exception_class' => $exception::class,
            'exception_code' => $exception->getCode(),
        ]);
    }
}
