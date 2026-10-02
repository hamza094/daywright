<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\DataTransferObjects\Paddle\PaddleSubscriptionSnapshot;
use App\Enums\Subscription\SubscriptionOperationType;
use App\Exceptions\Subscription\SubscriptionOperationValidationException;

final readonly class VerifySubscriptionOperation
{
    /**
     * Validate that the remote snapshot proves the operation succeeded.
     *
     * @throws SubscriptionOperationValidationException
     */
    public function execute(
        SubscriptionOperationType $type,
        string $requestedSubscriptionId,
        ?string $targetPlan,
        PaddleSubscriptionSnapshot $snapshot,
    ): void {
        // Validate subscription ID matches requested ID
        if ($snapshot->subscription_id !== $requestedSubscriptionId) {
            throw new SubscriptionOperationValidationException(
                'Remote subscription ID does not match requested ID',
                [
                    'requested_id' => $requestedSubscriptionId,
                    'remote_id' => $snapshot->subscription_id,
                ]
            );
        }

        // Validate based on operation type
        match ($type) {
            SubscriptionOperationType::Swap => $this->verifySwap($targetPlan, $snapshot),
            SubscriptionOperationType::Cancel => $this->verifyCancel($snapshot),
        };
    }

    /**
     * @throws SubscriptionOperationValidationException
     */
    private function verifySwap(?string $targetPlan, PaddleSubscriptionSnapshot $snapshot): void
    {
        if ($targetPlan === null) {
            throw new SubscriptionOperationValidationException('Target plan is required for swap verification');
        }

        if (! $snapshot->provesSwapSucceeded($targetPlan)) {
            throw new SubscriptionOperationValidationException(
                'Remote snapshot does not prove swap succeeded',
                [
                    'target_plan' => $targetPlan,
                    'remote_plan' => $snapshot->plan_id,
                    'remote_status' => $snapshot->status,
                ]
            );
        }
    }

    /**
     * @throws SubscriptionOperationValidationException
     */
    private function verifyCancel(PaddleSubscriptionSnapshot $snapshot): void
    {
        if (! $snapshot->provesCancelSucceeded()) {
            throw new SubscriptionOperationValidationException(
                'Remote snapshot does not prove cancel succeeded',
                [
                    'remote_status' => $snapshot->status,
                    'cancellation_effective_date' => $snapshot->cancellation_effective_date,
                ]
            );
        }
    }
}
