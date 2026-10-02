<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\DataTransferObjects\Paddle\PaddleSubscriptionSnapshot;
use App\Enums\Subscription\SubscriptionOperationRecoveryOutcome;
use App\Enums\Subscription\SubscriptionOperationStatus;
use App\Enums\Subscription\SubscriptionOperationType;
use App\Exceptions\Subscription\SubscriptionOperationValidationException;
use App\Interfaces\Paddle\CashierGatewayInterface;
use App\Models\SubscriptionOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Recovers one uncertain Paddle subscription mutation without sending it again.
 *
 * The action claims the operation, reads Paddle, verifies the exact expected
 * remote state, and then finalizes the local subscription in a short transaction.
 * Failed reads are retried with backoff and eventually moved to manual review.
 */
final readonly class RecoverSubscriptionOperation
{
    private const int CLAIM_LEASE_MINUTES = 5;

    private const int MAX_RECOVERY_ATTEMPTS = 5;

    public function __construct(
        private CashierGatewayInterface $paddleGateway,
        private VerifySubscriptionOperation $operationVerifier,
    ) {}

    public function execute(SubscriptionOperation $operation): SubscriptionOperationRecoveryOutcome
    {
        $claimToken = $this->claimForRecovery($operation);

        if ($claimToken === null) {
            return SubscriptionOperationRecoveryOutcome::Skipped;
        }

        $operation->refresh();

        if (blank($operation->paddle_subscription_id)) {
            return $this->rescheduleAfterFailedRead($operation, $claimToken);
        }

        $remoteSnapshot = $this->readRemoteSnapshot($operation);

        if (! $remoteSnapshot instanceof PaddleSubscriptionSnapshot || ! $this->provesOperationSucceeded($operation, $remoteSnapshot)) {
            return $this->rescheduleAfterFailedRead($operation, $claimToken);
        }

        return $this->completeVerifiedOperation($operation, $claimToken, $remoteSnapshot);
    }

    /**
     * Claim ownership atomically so two recovery workers cannot process one row.
     * The lease lets another worker take over if the current process disappears.
     */
    private function claimForRecovery(SubscriptionOperation $operation): ?string
    {
        $claimToken = (string) Str::uuid();
        $now = now();

        $claimed = SubscriptionOperation::query()
            ->whereKey($operation->id)
            ->readyForRecoveryAt($now)
            ->update([
                'status' => SubscriptionOperationStatus::Processing,
                'claim_token' => $claimToken,
                'claimed_at' => $now,
                'claim_expires_at' => $now->copy()->addMinutes(self::CLAIM_LEASE_MINUTES),
            ]);

        return $claimed === 1 ? $claimToken : null;
    }

    private function readRemoteSnapshot(SubscriptionOperation $operation): ?PaddleSubscriptionSnapshot
    {
        try {
            return $this->paddleGateway->getSubscription($operation->paddle_subscription_id);
        } catch (Throwable $exception) {
            Log::warning('Failed reading remote subscription from Paddle during recovery', [
                'operation_uuid' => $operation->operation_uuid,
                'paddle_subscription_id' => $operation->paddle_subscription_id,
                'exception_class' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);

            return null;
        }
    }

    private function provesOperationSucceeded(
        SubscriptionOperation $operation,
        PaddleSubscriptionSnapshot $remoteSnapshot,
    ): bool {
        try {
            $this->operationVerifier->execute(
                $operation->type,
                (string) $operation->paddle_subscription_id,
                $this->targetPlanId($operation),
                $remoteSnapshot,
            );

            return true;
        } catch (SubscriptionOperationValidationException $exception) {
            Log::warning('Remote snapshot does not prove operation succeeded', [
                'operation_uuid' => $operation->operation_uuid,
                'operation_type' => $operation->type->value,
                'error' => $exception->getMessage(),
                'context' => $exception->getContext(),
            ]);

            return false;
        }
    }

    private function targetPlanId(SubscriptionOperation $operation): ?string
    {
        if ($operation->type !== SubscriptionOperationType::Swap) {
            return null;
        }

        return (string) config("services.paddle.{$operation->target_plan}");
    }

    /**
     * Finalize only while this worker still owns a live claim. The row lock and
     * token check prevent an expired worker from overwriting a successor.
     */
    private function completeVerifiedOperation(
        SubscriptionOperation $operation,
        string $claimToken,
        PaddleSubscriptionSnapshot $remoteSnapshot,
    ): SubscriptionOperationRecoveryOutcome {
        return DB::transaction(function () use ($operation, $claimToken, $remoteSnapshot): SubscriptionOperationRecoveryOutcome {
            $claimedOperation = $this->lockLiveClaim($operation, $claimToken);

            if (! $claimedOperation instanceof SubscriptionOperation) {
                return SubscriptionOperationRecoveryOutcome::Skipped;
            }

            $localSubscription = $claimedOperation->subscription
                ?? $claimedOperation->user->subscription($claimedOperation->user->subscriptionName());

            if ($localSubscription === null) {
                $this->moveToManualReview($claimedOperation);

                return SubscriptionOperationRecoveryOutcome::ManualReview;
            }

            $localSubscription->update($this->localSubscriptionChanges($operation, $remoteSnapshot));
            $claimedOperation->transitionTo(SubscriptionOperationStatus::Completed, [
                'completed_at' => now(),
                'attempts' => $claimedOperation->attempts + 1,
                'last_attempt_at' => now(),
            ]);

            return SubscriptionOperationRecoveryOutcome::Recovered;
        });
    }

    private function lockLiveClaim(SubscriptionOperation $operation, string $claimToken): ?SubscriptionOperation
    {
        return SubscriptionOperation::query()
            ->unexpiredClaimOwnedBy($claimToken, now())
            ->whereKey($operation->id)
            ->lockForUpdate()
            ->first();
    }

    /** @return array<string, int|string|null> */
    private function localSubscriptionChanges(
        SubscriptionOperation $operation,
        PaddleSubscriptionSnapshot $remoteSnapshot,
    ): array {
        return match ($operation->type) {
            SubscriptionOperationType::Swap => [
                'paddle_plan' => (int) config("services.paddle.{$operation->target_plan}"),
                'paddle_status' => 'active',
                'ends_at' => null,
            ],
            SubscriptionOperationType::Cancel => [
                'paddle_status' => 'deleted',
                'ends_at' => $remoteSnapshot->cancellation_effective_date,
            ],
        };
    }

    private function rescheduleAfterFailedRead(
        SubscriptionOperation $operation,
        string $claimToken,
    ): SubscriptionOperationRecoveryOutcome {
        return DB::transaction(function () use ($operation, $claimToken): SubscriptionOperationRecoveryOutcome {
            $claimedOperation = $this->lockLiveClaim($operation, $claimToken);

            if (! $claimedOperation instanceof SubscriptionOperation) {
                return SubscriptionOperationRecoveryOutcome::Skipped;
            }

            $nextAttempt = $claimedOperation->attempts + 1;

            if ($nextAttempt >= self::MAX_RECOVERY_ATTEMPTS) {
                $this->moveToManualReview($claimedOperation, $nextAttempt);

                return SubscriptionOperationRecoveryOutcome::ManualReview;
            }

            $claimedOperation->transitionTo(SubscriptionOperationStatus::Unknown, [
                'available_at' => now()->addMinutes($this->backoffMinutes($nextAttempt)),
                'attempts' => $nextAttempt,
                'last_attempt_at' => now(),
            ]);

            return SubscriptionOperationRecoveryOutcome::Unresolved;
        });
    }

    private function backoffMinutes(int $attempt): int
    {
        return match ($attempt) {
            1 => 1,
            2 => 5,
            3 => 15,
            default => 60,
        };
    }

    private function moveToManualReview(SubscriptionOperation $operation, ?int $attempt = null): void
    {
        $operation->transitionTo(SubscriptionOperationStatus::ManualReview, [
            'attempts' => $attempt ?? $operation->attempts + 1,
            'last_attempt_at' => now(),
        ]);
    }
}
