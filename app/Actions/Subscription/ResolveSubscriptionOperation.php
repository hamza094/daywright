<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Enums\Subscription\SubscriptionOperationStatus;
use App\Enums\Subscription\SubscriptionOperationType;
use App\Exceptions\Subscription\SubscriptionOperationValidationException;
use App\Interfaces\Paddle\CashierGatewayInterface;
use App\Models\SubscriptionOperation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Throwable;

final readonly class ResolveSubscriptionOperation
{
    public function __construct(
        private CashierGatewayInterface $cashier,
        private VerifySubscriptionOperation $verify,
    ) {}

    public function markCompleted(SubscriptionOperation $operation): SubscriptionOperation
    {
        // Only resolve manual_review rows
        if ($operation->status !== SubscriptionOperationStatus::ManualReview) {
            throw new LogicException("Operation {$operation->operation_uuid} is not in manual_review state [{$operation->status->value}].");
        }

        if (blank($operation->paddle_subscription_id)) {
            throw new LogicException("Operation {$operation->operation_uuid} has no Paddle subscription ID recorded for verification.");
        }

        try {
            $remoteSub = $this->cashier->getSubscription($operation->paddle_subscription_id);
        } catch (Throwable $e) {
            throw new LogicException('Cannot mark completed: Paddle could not be read.', 0, $e);
        }

        if ($remoteSub === null) {
            throw new LogicException("Cannot mark completed: Subscription {$operation->paddle_subscription_id} not found in Paddle.");
        }

        // Use VerifySubscriptionOperation for exact remote-result validation
        try {
            $targetPlanId = $operation->type === SubscriptionOperationType::Swap
                ? (string) config("services.paddle.{$operation->target_plan}")
                : null;

            $this->verify->execute(
                $operation->type,
                $operation->paddle_subscription_id,
                $targetPlanId,
                $remoteSub,
            );
        } catch (SubscriptionOperationValidationException $e) {
            throw new LogicException("Cannot mark completed: {$e->getMessage()}", 0, $e);
        }

        return DB::transaction(function () use ($operation, $remoteSub): SubscriptionOperation {
            $current = SubscriptionOperation::query()->whereKey($operation->id)->lockForUpdate()->firstOrFail();

            if ($current->status !== SubscriptionOperationStatus::ManualReview) {
                throw new LogicException('Operation is no longer awaiting manual review.');
            }

            $localSub = $current->subscription ?? $current->user->subscription($current->user->subscriptionName());

            if ($localSub === null) {
                throw new LogicException('Cannot mark completed: local subscription is missing.');
            }

            $localSub->update(match ($current->type) {
                SubscriptionOperationType::Swap => [
                    'paddle_plan' => (int) config("services.paddle.{$current->target_plan}"),
                    'paddle_status' => 'active',
                    'ends_at' => null,
                ],
                SubscriptionOperationType::Cancel => [
                    'paddle_status' => 'deleted',
                    'ends_at' => $remoteSub->cancellation_effective_date,
                ],
            });

            $current->transitionTo(SubscriptionOperationStatus::Completed, ['completed_at' => now()]);

            return $current;
        });
    }

    public function markFailed(SubscriptionOperation $operation, string $reference): SubscriptionOperation
    {
        if (blank($reference)) {
            throw new InvalidArgumentException('A ticket or incident reference is required to mark an operation failed.');
        }

        // Only resolve manual_review rows
        if ($operation->status !== SubscriptionOperationStatus::ManualReview) {
            throw new LogicException("Operation {$operation->operation_uuid} is not in manual_review state [{$operation->status->value}].");
        }

        return DB::transaction(function () use ($operation, $reference): SubscriptionOperation {
            $current = SubscriptionOperation::query()->whereKey($operation->id)->lockForUpdate()->firstOrFail();

            if ($current->status !== SubscriptionOperationStatus::ManualReview) {
                throw new LogicException('Operation is no longer awaiting manual review.');
            }

            $current->transitionTo(SubscriptionOperationStatus::Failed, [
                'failed_at' => now(),
                'last_error_class' => 'ManualResolution',
                'last_error_code' => $reference,
            ]);

            return $current;
        });
    }
}
