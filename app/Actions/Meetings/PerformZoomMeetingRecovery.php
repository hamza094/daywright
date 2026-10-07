<?php

declare(strict_types=1);

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\MeetingLockOperations;
use App\DataTransferObjects\Zoom\Meeting as ZoomMeeting;
use App\Enums\Meeting\MeetingSyncOperationType;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Exceptions\Integrations\Zoom\ZoomException;
use App\Exceptions\Integrations\Zoom\ZoomRateLimitException;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Services\Project\MeetingOperationLock;
use App\Services\Project\MeetingSyncErrorFormatter;
use App\Services\Zoom\ZoomRecoveryPolicy;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

use function Safe\json_decode;

final readonly class PerformZoomMeetingRecovery
{
    use MeetingLockOperations;

    public function __construct(
        private MeetingSyncErrorFormatter $errorFormatter,
        private ZoomRecoveryPolicy $policy,
        private MeetingOperationLock $operationLocks,
    ) {}

    public function execute(Meeting $meeting, string $operationId, string $claimToken, Zoom $zoom): void
    {
        if (! $this->policy->isClaimValid($meeting, $claimToken)) {
            Log::info('Claim token expired or invalid, skipping recovery', [
                'meeting_id' => $meeting->id,
                'expected_claim_token' => $claimToken,
                'current_claim_token' => $meeting->sync_claim_token,
            ]);

            return;
        }

        if (! $this->policy->isOperationCurrent($meeting, $operationId)) {
            Log::info('Operation ID changed, skipping recovery', [
                'meeting_id' => $meeting->id,
                'expected_operation_id' => $operationId,
                'current_operation_id' => $meeting->sync_operation_id,
            ]);

            return;
        }

        $user = $meeting->user;

        try {
            match ($meeting->sync_operation_type) {
                MeetingSyncOperationType::Update => $this->recoverUpdate($meeting, $user, $zoom, $operationId, $claimToken),
                MeetingSyncOperationType::Delete => $this->recoverDelete($meeting, $user, $zoom, $operationId, $claimToken),
            };
        } catch (ZoomException $exception) {
            report($exception);

            if ($this->isPermanentError($exception)) {
                $this->manualReview($meeting, 'permanent_error', $operationId, $claimToken);
            } else {
                $this->scheduleRetry($meeting, $exception, $operationId, $claimToken);
            }
        } catch (Throwable $exception) {
            report($exception);

            $this->scheduleRetry($meeting, $exception, $operationId, $claimToken);
        }
    }

    private function recoverUpdate(Meeting $meeting, \App\Models\User $user, Zoom $zoom, string $operationId, string $claimToken): void
    {
        $requestedPayload = $this->validateUpdatePayload($meeting, $operationId, $claimToken);

        if ($requestedPayload === null) {
            return;
        }

        $zoomMeeting = $this->findZoomMeeting($meeting, $user, $zoom);

        if ($zoomMeeting === null) {
            $this->scheduleRetry($meeting, null, $operationId, $claimToken);

            return;
        }

        if ($this->payloadMatches($requestedPayload, $zoomMeeting)) {
            $this->withValidatedOperationLock(
                $meeting,
                $operationId,
                $claimToken,
                MeetingSyncOperationType::Update,
                function (Meeting $currentMeeting) use ($requestedPayload, $operationId, $claimToken): void {
                    $this->applyUpdateLocally($currentMeeting, $requestedPayload, $operationId, $claimToken);
                },
            );
        } else {
            $this->retryUpdate($meeting, $user, $zoom, $operationId, $claimToken);
        }
    }

    private function recoverDelete(Meeting $meeting, \App\Models\User $user, Zoom $zoom, string $operationId, string $claimToken): void
    {
        $this->withValidatedOperationLock(
            $meeting,
            $operationId,
            $claimToken,
            MeetingSyncOperationType::Delete,
            function (Meeting $currentMeeting) use ($user, $zoom, $operationId, $claimToken): void {
                try {
                    $zoom->deleteMeeting($currentMeeting->meeting_id, $user);
                } catch (\App\Exceptions\Integrations\Zoom\NotFoundException) {
                    // The requested remote state already holds.
                }

                $this->finalizeDelete($currentMeeting, $operationId, $claimToken);
            },
        );
    }

    private function findZoomMeeting(Meeting $meeting, \App\Models\User $user, Zoom $zoom): ?ZoomMeeting
    {
        if ($meeting->meeting_id === null) {
            return null;
        }

        return $zoom->getMeeting($meeting->meeting_id, $user);
    }

    private function validateUpdatePayload(Meeting $meeting, string $operationId, string $claimToken): ?array
    {
        $payload = $meeting->sync_payload;

        // Check if payload is a non-empty string
        if (! is_string($payload) || trim($payload) === '') {
            $this->manualReview($meeting, 'invalid_payload', $operationId, $claimToken);

            return null;
        }

        // Try to decode JSON
        try {
            $decoded = json_decode($payload, true);
        } catch (JsonException) {
            $this->manualReview($meeting, 'invalid_payload', $operationId, $claimToken);

            return null;
        }

        // Check if decoded value is a non-empty array
        if (! is_array($decoded) || $decoded === []) {
            $this->manualReview($meeting, 'invalid_payload', $operationId, $claimToken);

            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyUpdateLocally(Meeting $meeting, array $payload, string $operationId, string $claimToken): void
    {
        DB::transaction(function () use ($meeting, $payload, $operationId, $claimToken): void {
            $lockedMeeting = $this->lockMeeting($meeting);

            if (! $this->policy->isClaimValid($lockedMeeting, $claimToken) || ! $this->policy->isOperationCurrent($lockedMeeting, $operationId)) {
                return;
            }

            $updateData = [];

            foreach ($this->getSupportedUpdateFields() as $field) {
                if (array_key_exists($field, $payload)) {
                    $updateData[$field] = $payload[$field];
                }
            }

            $lockedMeeting->update($updateData + [
                'sync_status' => MeetingSyncStatus::Active,
                'sync_operation_id' => null,
                'sync_operation_type' => null,
                'sync_payload' => null,
                'sync_error' => null,
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => null,
                'synced_at' => now(),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function retryUpdate(Meeting $meeting, \App\Models\User $user, Zoom $zoom, string $operationId, string $claimToken): void
    {
        $this->withValidatedOperationLock(
            $meeting,
            $operationId,
            $claimToken,
            MeetingSyncOperationType::Update,
            function (Meeting $currentMeeting) use ($user, $zoom, $operationId, $claimToken): void {
                $payload = $this->validateUpdatePayload($currentMeeting, $operationId, $claimToken);

                if ($payload === null) {
                    return;
                }

                $zoom->updateMeeting($payload + ['meeting_id' => $currentMeeting->meeting_id], $user);

                $this->applyUpdateLocally($currentMeeting, $payload, $operationId, $claimToken);
            },
        );
    }

    /**
     * Acquire the same lock used by user initiated meeting operations, then
     * reload and validate ownership immediately before any recovery write.
     *
     * @param  Closure(Meeting): void  $callback
     */
    private function withValidatedOperationLock(
        Meeting $meeting,
        string $operationId,
        string $claimToken,
        MeetingSyncOperationType $operationType,
        Closure $callback,
    ): void {
        $this->operationLocks->block(
            key: $this->meetingLockKey($meeting),
            conflictMessage: 'A meeting operation is currently in progress. Recovery will retry.',
            callback: function () use ($meeting, $operationId, $claimToken, $operationType, $callback): void {
                $currentMeeting = $this->findMeetingOrFail($meeting);

                if ($currentMeeting->sync_operation_type !== $operationType
                    || ! $this->policy->isClaimValid($currentMeeting, $claimToken)
                    || ! $this->policy->isOperationCurrent($currentMeeting, $operationId)) {
                    Log::info('Recovery ownership changed before remote meeting mutation', [
                        'meeting_id' => $currentMeeting->id,
                        'expected_operation_id' => $operationId,
                        'current_operation_id' => $currentMeeting->sync_operation_id,
                        'expected_claim_token' => $claimToken,
                        'current_claim_token' => $currentMeeting->sync_claim_token,
                    ]);

                    return;
                }

                $callback($currentMeeting);
            },
        );
    }

    private function finalizeDelete(Meeting $meeting, string $operationId, string $claimToken): void
    {
        DB::transaction(function () use ($meeting, $operationId, $claimToken): void {
            $lockedMeeting = $this->lockMeeting($meeting);

            if (! $this->policy->isClaimValid($lockedMeeting, $claimToken) || ! $this->policy->isOperationCurrent($lockedMeeting, $operationId)) {
                return;
            }

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

    private function scheduleRetry(Meeting $meeting, ?Throwable $exception, string $operationId, string $claimToken): void
    {
        DB::transaction(function () use ($meeting, $exception, $operationId, $claimToken): void {
            $lockedMeeting = $this->lockMeeting($meeting);

            if (! $this->policy->isClaimValid($lockedMeeting, $claimToken) || ! $this->policy->isOperationCurrent($lockedMeeting, $operationId)) {
                return;
            }

            $nextAttempt = $lockedMeeting->sync_attempts + 1;

            if (! $this->policy->shouldRetry($nextAttempt)) {
                $this->manualReview($lockedMeeting, 'max_attempts_reached', $operationId, $claimToken);

                return;
            }

            $retryDelay = $this->getRetryDelay($exception, $lockedMeeting->sync_attempts);

            $lockedMeeting->update([
                'sync_attempts' => $nextAttempt,
                'sync_error' => $exception ? $this->errorFormatter->format($exception) : 'zoom_meeting_not_found',
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => now()->addSeconds(max(1, $retryDelay)),
            ]);
        });
    }

    private function manualReview(Meeting $meeting, string $reason, string $operationId, string $claimToken): void
    {
        DB::transaction(function () use ($meeting, $reason, $operationId, $claimToken): void {
            $lockedMeeting = $this->lockMeeting($meeting);

            if (! $this->policy->isClaimValid($lockedMeeting, $claimToken) || ! $this->policy->isOperationCurrent($lockedMeeting, $operationId)) {
                return;
            }

            $failedStatus = match ($lockedMeeting->sync_operation_type) {
                MeetingSyncOperationType::Update => MeetingSyncStatus::UpdateFailed,
                MeetingSyncOperationType::Delete => MeetingSyncStatus::DeleteFailed,
                default => null,
            };

            if ($failedStatus === null) {
                return;
            }

            $lockedMeeting->update([
                'sync_status' => $failedStatus,
                'sync_error' => $reason,
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => null,
            ]);
        });

        Log::warning('Zoom meeting recovery requires manual review', [
            'meeting_id' => $meeting->id,
            'reason' => $reason,
        ]);
    }

    private function getRetryDelay(?Throwable $exception, int $currentAttempt): int
    {
        if ($exception instanceof ZoomRateLimitException && $exception->retryAfterSeconds() !== null) {
            return $exception->retryAfterSeconds();
        }

        return $this->policy->getBackoffSeconds($currentAttempt);
    }

    /**
     * @param  array<string, mixed>  $requested
     */
    private function payloadMatches(array $requested, ZoomMeeting $zoomMeeting): bool
    {
        return collect($this->getSupportedUpdateFields())
            ->filter(fn ($field) => array_key_exists($field, $requested))
            ->every(fn ($field) => ($requested[$field] ?? null) === ($zoomMeeting->{$field} ?? null)
            );
    }

    /**
     * @return list<string>
     */
    private function getSupportedUpdateFields(): array
    {
        return ['topic', 'duration', 'agenda', 'timezone', 'start_time', 'password', 'join_before_host'];
    }

    private function isPermanentError(ZoomException $exception): bool
    {
        $statusCode = $exception->status();

        return $statusCode >= 400 && $statusCode < 500
            && $statusCode !== 429;
    }
}
