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
use App\Services\Webhooks\ZoomMeetingUpdatedWebhookChanges;
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
        private ZoomMeetingUpdatedWebhookChanges $changes,
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
        $requestedPayload = $this->decodeValidUpdatePayload($meeting, $operationId, $claimToken);

        if ($requestedPayload === null) {
            return;
        }

        if ($meeting->meeting_id === null) {
            $this->scheduleRetry($meeting, null, $operationId, $claimToken);

            return;
        }

        $currentZoomMeeting = $this->fetchZoomMeeting($meeting, $user, $zoom);

        if ($currentZoomMeeting === null) {
            // Zoom confirmed the meeting is gone, so finish locally instead of retrying the update.
            $this->withValidatedOperationLock(
                $meeting,
                $operationId,
                $claimToken,
                MeetingSyncOperationType::Update,
                function (Meeting $currentMeeting) use ($operationId, $claimToken): void {
                    $this->finalizeMeetingAsDeleted($currentMeeting, $operationId, $claimToken);
                },
            );

            return;
        }

        if ($this->changes->zoomHasRequestedFields($requestedPayload, $currentZoomMeeting)) {
            // Zoom already has what we asked for — save the full Zoom snapshot (including
            // the refreshed join_url) and clear the operation.
            $this->withValidatedOperationLock(
                $meeting,
                $operationId,
                $claimToken,
                MeetingSyncOperationType::Update,
                function (Meeting $currentMeeting) use ($currentZoomMeeting, $operationId, $claimToken): void {
                    $this->applyUpdateLocally(
                        $currentMeeting,
                        $this->changes->fieldsFromZoom($currentZoomMeeting),
                        $operationId,
                        $claimToken,
                    );
                },
            );
        } elseif ($this->isPasswordOperation($requestedPayload)) {
            // For password operations the GET proved Zoom still has the old value.
            // Consume one recovery cycle now; if the limit is reached, stop.
            if (! $this->consumeMismatchCycle($meeting, $operationId, $claimToken)) {
                return;
            }

            // Send the PATCH again, then leave the operation open so the next recovery
            // cycle can do another GET before finalising locally.
            $this->retryPasswordUpdate($meeting, $user, $zoom, $operationId, $claimToken);
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

                $this->finalizeMeetingAsDeleted($currentMeeting, $operationId, $claimToken);
            },
        );
    }

    private function fetchZoomMeeting(Meeting $meeting, \App\Models\User $user, Zoom $zoom): ?ZoomMeeting
    {
        if ($meeting->meeting_id === null) {
            return null;
        }

        return $zoom->getMeeting($meeting->meeting_id, $user);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeValidUpdatePayload(Meeting $meeting, string $operationId, string $claimToken): ?array
    {
        $payload = $meeting->sync_payload;

        if (! is_string($payload) || trim($payload) === '') {
            $this->manualReview($meeting, 'invalid_payload', $operationId, $claimToken);

            return null;
        }

        try {
            $decoded = json_decode($payload, true);
        } catch (JsonException) {
            $this->manualReview($meeting, 'invalid_payload', $operationId, $claimToken);

            return null;
        }

        if (! is_array($decoded) || $decoded === []) {
            $this->manualReview($meeting, 'invalid_payload', $operationId, $claimToken);

            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $updateData
     */
    private function applyUpdateLocally(Meeting $meeting, array $updateData, string $operationId, string $claimToken): void
    {
        DB::transaction(function () use ($meeting, $updateData, $operationId, $claimToken): void {
            $lockedMeeting = $this->lockMeeting($meeting);

            if (! $this->policy->isClaimValid($lockedMeeting, $claimToken) || ! $this->policy->isOperationCurrent($lockedMeeting, $operationId)) {
                return;
            }

            $fieldsToUpdate = [];

            foreach (ZoomMeetingUpdatedWebhookChanges::ALLOWED_FIELDS as $field) {
                if (array_key_exists($field, $updateData)) {
                    $fieldsToUpdate[$field] = $updateData[$field];
                }
            }

            $lockedMeeting->update($fieldsToUpdate + [
                'sync_status' => MeetingSyncStatus::Active,
                'sync_operation_id' => null,
                'sync_operation_type' => null,
                'sync_payload' => null,
                'sync_error' => null,
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => null,
                'synced_at' => now(),
                'sync_reconcile_before_at' => now(),
            ]);
        });
    }

    private function retryUpdate(Meeting $meeting, \App\Models\User $user, Zoom $zoom, string $operationId, string $claimToken): void
    {
        $this->withValidatedOperationLock(
            $meeting,
            $operationId,
            $claimToken,
            MeetingSyncOperationType::Update,
            function (Meeting $currentMeeting) use ($user, $zoom, $operationId, $claimToken): void {
                $payload = $this->decodeValidUpdatePayload($currentMeeting, $operationId, $claimToken);

                if ($payload === null) {
                    return;
                }

                // Record when this retry starts so an older webhook cannot finish this newer attempt.
                $currentMeeting->update(['sync_started_at' => now()]);
                $zoom->updateMeeting($payload + ['meeting_id' => $currentMeeting->meeting_id], $user);

                $this->applyUpdateLocally($currentMeeting, $payload, $operationId, $claimToken);
            },
        );
    }

    /**
     * For a password PATCH retry: send the update to Zoom and then leave the
     * operation open (claim cleared, available_at +1 min) so the next recovery
     * cycle performs a read-only GET before finalising locally.
     *
     * A successful PATCH here does NOT increment sync_attempts; only a GET
     * mismatch counts as a failed cycle (consumed before calling this method).
     */
    private function retryPasswordUpdate(Meeting $meeting, \App\Models\User $user, Zoom $zoom, string $operationId, string $claimToken): void
    {
        $this->withValidatedOperationLock(
            $meeting,
            $operationId,
            $claimToken,
            MeetingSyncOperationType::Update,
            function (Meeting $currentMeeting) use ($user, $zoom, $operationId, $claimToken): void {
                $payload = $this->decodeValidUpdatePayload($currentMeeting, $operationId, $claimToken);

                if ($payload === null) {
                    return;
                }

                $currentMeeting->update(['sync_started_at' => now()]);
                $zoom->updateMeeting($payload + ['meeting_id' => $currentMeeting->meeting_id], $user);

                // PATCH succeeded — leave pending for a later GET instead of finalising.
                $this->scheduleVerification($currentMeeting, $operationId, $claimToken);
            },
        );
    }

    /**
     * Consume one mismatch cycle. Returns false when the limit is reached and
     * the meeting has already been moved to UpdateFailed.
     */
    private function consumeMismatchCycle(Meeting $meeting, string $operationId, string $claimToken): bool
    {
        $shouldContinue = true;

        DB::transaction(function () use ($meeting, $operationId, $claimToken, &$shouldContinue): void {
            $lockedMeeting = $this->lockMeeting($meeting);

            if (! $this->policy->isClaimValid($lockedMeeting, $claimToken) || ! $this->policy->isOperationCurrent($lockedMeeting, $operationId)) {
                $shouldContinue = false;

                return;
            }

            $nextAttempt = $lockedMeeting->sync_attempts + 1;

            if (! $this->policy->shouldRetry($nextAttempt)) {
                // Keep operation ID and payload for manual review.
                $lockedMeeting->update([
                    'sync_status' => MeetingSyncStatus::UpdateFailed,
                    'sync_error' => 'max_attempts_reached',
                    'sync_attempts' => $nextAttempt,
                    'sync_claim_token' => null,
                    'sync_lease_expires_at' => null,
                    'sync_available_at' => null,
                ]);

                Log::warning('Zoom meeting recovery requires manual review', [
                    'meeting_id' => $meeting->id,
                    'reason' => 'max_attempts_reached',
                ]);

                $shouldContinue = false;

                return;
            }

            $lockedMeeting->update(['sync_attempts' => $nextAttempt]);
        });

        return $shouldContinue;
    }

    /**
     * Clear the claim and lease and set sync_available_at to one minute from now
     * so the scheduler picks it up for a read-only GET verification cycle.
     */
    private function scheduleVerification(Meeting $meeting, string $operationId, string $claimToken): void
    {
        DB::transaction(function () use ($meeting, $operationId, $claimToken): void {
            $lockedMeeting = $this->lockMeeting($meeting);

            if (! $this->policy->isClaimValid($lockedMeeting, $claimToken) || ! $this->policy->isOperationCurrent($lockedMeeting, $operationId)) {
                return;
            }

            $lockedMeeting->update([
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => now()->addMinute(),
                'sync_error' => null,
            ]);
        });
    }

    private function isPasswordOperation(array $payload): bool
    {
        return array_key_exists('password', $payload);
    }

    /**
     * Take the same lock as user-initiated changes. Reload the meeting and
     * check that this recovery still owns it before writing to Zoom.
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

    private function finalizeMeetingAsDeleted(Meeting $meeting, string $operationId, string $claimToken): void
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
                'sync_payload' => null,
                'sync_error' => null,
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => null,
                'synced_at' => now(),
                'sync_reconcile_before_at' => now(),
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

    private function isPermanentError(ZoomException $exception): bool
    {
        $statusCode = $exception->status();

        return $statusCode >= 400 && $statusCode < 500
            && $statusCode !== 429;
    }
}
