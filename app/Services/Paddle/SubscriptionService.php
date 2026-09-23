<?php

declare(strict_types=1);

namespace App\Services\Paddle;

use App\DataTransferObjects\Subscription\SubscriptionOperationResult;
use App\Enums\Subscription\SubscriptionOperationStatus;
use App\Enums\Subscription\SubscriptionOperationType;
use App\Exceptions\Paddle\ActiveOperationConflictException;
use App\Exceptions\Paddle\IdempotencyMismatchException;
use App\Exceptions\Paddle\PaddleUnavailableException;
use App\Exceptions\Paddle\SubscriptionException;
use App\Interfaces\Paddle;
use App\Interfaces\Paddle\CashierGatewayInterface;
use App\Models\SubscriptionOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Paddle\Exceptions\PaddleException;
use Override;
use Throwable;

final class SubscriptionService implements Paddle
{
    private const int CLAIM_LEASE_MINUTES = 5;

    public function __construct(private readonly CashierGatewayInterface $cashier) {}

    #[Override]
    public function subscribe(User $user, string $plan): string
    {
        $planId = $this->resolvePlanId($plan, 'subscribe');

        $user = $user->loadMissing('subscriptions', 'customer');
        $this->validateSubscribeAllowed($user, $plan);

        $appUrl = rtrim((string) config('app.url'), '/');

        try {
            return $this->cashier->generatePayLink($user, $planId, "{$appUrl}/subscriptions");
        } catch (Throwable $e) {
            Log::error('Paddle API Exception during subscribe', [
                'user_id' => $user->id,
                'plan' => $plan,
                'exception_class' => $e::class,
                'exception_code' => $e->getCode(),
            ]);
            throw $e;
        }
    }

    #[Override]
    public function swap(User $user, string $plan, string $idempotencyKey): SubscriptionOperationResult
    {
        $planId = $this->resolvePlanId($plan, 'swap');
        $user = $user->loadMissing('subscriptions', 'customer');

        $operation = $this->findExistingOperation($user, SubscriptionOperationType::Swap, $plan, $idempotencyKey);

        if ($operation !== null) {
            return $this->returnExistingOperation($operation, SubscriptionOperationType::Swap, $plan);
        }

        $this->validateSwapAllowed($user, $plan);

        $operation = $this->createProcessingOperation(
            user: $user,
            type: SubscriptionOperationType::Swap,
            plan: $plan,
            idempotencyKey: $idempotencyKey,
        );

        if (! $operation->wasRecentlyCreated) {
            return $this->returnExistingOperation($operation, SubscriptionOperationType::Swap, $plan);
        }

        return $this->callPaddleOnce($user, $operation, $planId);
    }

    #[Override]
    public function cancel(User $user, string $plan, string $idempotencyKey): SubscriptionOperationResult
    {
        $user = $user->loadMissing('subscriptions', 'customer');

        $operation = $this->findExistingOperation($user, SubscriptionOperationType::Cancel, $plan, $idempotencyKey);

        if ($operation !== null) {
            return $this->returnExistingOperation($operation, SubscriptionOperationType::Cancel, $plan);
        }

        $this->validateCancelAllowed($user, $plan);

        $operation = $this->createProcessingOperation(
            user: $user,
            type: SubscriptionOperationType::Cancel,
            plan: $plan,
            idempotencyKey: $idempotencyKey,
        );

        if (! $operation->wasRecentlyCreated) {
            return $this->returnExistingOperation($operation, SubscriptionOperationType::Cancel, $plan);
        }

        return $this->callPaddleOnce($user, $operation);
    }

    private function findExistingOperation(
        User $user,
        SubscriptionOperationType $type,
        string $plan,
        string $idempotencyKey,
    ): ?SubscriptionOperation {
        $idempotencyKeyHash = hash('sha256', $idempotencyKey);
        $fingerprint = hash('sha256', "{$user->id}:{$type->value}:{$plan}");

        $existing = SubscriptionOperation::query()
            ->where('user_id', $user->id)
            ->where('idempotency_key_hash', $idempotencyKeyHash)
            ->first();

        if ($existing !== null) {
            // Compare fingerprints - different action or plan is a mismatch
            if ($existing->request_fingerprint !== $fingerprint) {
                throw new IdempotencyMismatchException;
            }
        }

        return $existing;
    }

    private function returnExistingOperation(
        SubscriptionOperation $operation,
        SubscriptionOperationType $type,
        string $plan,
    ): SubscriptionOperationResult {
        return new SubscriptionOperationResult(
            operation: $operation->fresh(),
            message: $this->getMessageForStatus($operation->status, $type, $plan),
        );
    }

    private function createProcessingOperation(
        User $user,
        SubscriptionOperationType $type,
        string $plan,
        string $idempotencyKey,
    ): SubscriptionOperation {
        $idempotencyKeyHash = hash('sha256', $idempotencyKey);
        $fingerprint = hash('sha256', "{$user->id}:{$type->value}:{$plan}");

        return DB::transaction(function () use ($user, $type, $plan, $idempotencyKeyHash, $fingerprint): SubscriptionOperation {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $existing = SubscriptionOperation::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key_hash', $idempotencyKeyHash)
                ->first();

            if ($existing !== null) {
                if ($existing->request_fingerprint !== $fingerprint) {
                    throw new IdempotencyMismatchException;
                }

                return $existing;
            }

            // Check for active operation conflict
            $activeConflict = SubscriptionOperation::query()
                ->where('user_id', $user->id)
                ->active()
                ->exists();

            if ($activeConflict) {
                throw new ActiveOperationConflictException;
            }

            $paddleSubscription = $user->subscription($user->subscriptionName());
            $paddleSubscriptionId = $paddleSubscription !== null ? (string) $paddleSubscription->paddle_id : '';
            $claimToken = (string) Str::uuid();

            /** @var SubscriptionOperation $operation */
            $operation = SubscriptionOperation::create([
                'operation_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'subscription_id' => $paddleSubscription?->id,
                'paddle_subscription_id' => $paddleSubscriptionId,
                'type' => $type,
                'target_plan' => $plan,
                'idempotency_key_hash' => $idempotencyKeyHash,
                'request_fingerprint' => $fingerprint,
                'status' => SubscriptionOperationStatus::Processing,
                'attempts' => 0,
                'claim_token' => $claimToken,
                'claimed_at' => now(),
                'claim_expires_at' => now()->addMinutes(self::CLAIM_LEASE_MINUTES),
                'provider_attempted_at' => now(),
            ]);

            return $operation;
        });
    }

    private function callPaddleOnce(
        User $user,
        SubscriptionOperation $operation,
        ?int $planId = null,
    ): SubscriptionOperationResult {
        $paddleSubscription = $user->subscription($user->subscriptionName());
        $plan = $operation->target_plan;

        try {
            match ($operation->type) {
                SubscriptionOperationType::Swap => $this->cashier->swapAndInvoice($paddleSubscription, $planId),
                SubscriptionOperationType::Cancel => $this->cashier->cancel($paddleSubscription),
            };

            return $this->recordSuccess($operation, $plan);
        } catch (PaddleException $e) {
            return $this->recordFailure($operation, $plan, $e);
        } catch (Throwable $e) {
            return $this->recordUnknown($operation, $plan, $e);
        }
    }

    private function recordSuccess(SubscriptionOperation $operation, string $plan): SubscriptionOperationResult
    {
        $updated = SubscriptionOperation::query()
            ->where('id', $operation->id)
            ->where('claim_token', $operation->claim_token)
            ->where('status', SubscriptionOperationStatus::Processing)
            ->update([
                'status' => SubscriptionOperationStatus::Completed,
                'completed_at' => now(),
                'last_attempt_at' => now(),
            ]);

        if ($updated === 0) {
            // Lost the race - another worker completed it
            $operation->refresh();

            return new SubscriptionOperationResult(
                operation: $operation->fresh(),
                message: 'Your request is being processed. We will confirm the result shortly.',
            );
        }

        return new SubscriptionOperationResult(
            operation: SubscriptionOperation::find($operation->id),
            message: $operation->type === SubscriptionOperationType::Swap
                ? "Your subscription has been successfully updated to the {$plan} plan"
                : 'Your subscription has been canceled successfully.',
        );
    }

    private function recordFailure(SubscriptionOperation $operation, string $plan, PaddleException $e): SubscriptionOperationResult
    {
        Log::warning('Paddle rejected operation', [
            'operation_uuid' => $operation->operation_uuid,
            'type' => $operation->type->value,
            'plan' => $plan,
            'exception_class' => $e::class,
            'exception_code' => $e->getCode(),
        ]);

        $updated = SubscriptionOperation::query()
            ->where('id', $operation->id)
            ->where('claim_token', $operation->claim_token)
            ->where('status', SubscriptionOperationStatus::Processing)
            ->update([
                'status' => SubscriptionOperationStatus::Failed,
                'failed_at' => now(),
                'last_attempt_at' => now(),
                'last_error_class' => $e::class,
                'last_error_code' => (string) $e->getCode(),
            ]);

        if ($updated === 0) {
            return $this->returnExistingOperation($operation->fresh(), $operation->type, $plan);
        }

        throw new PaddleUnavailableException('The subscription operation could not be completed.');
    }

    private function recordUnknown(SubscriptionOperation $operation, string $plan, Throwable $e): SubscriptionOperationResult
    {
        Log::error('Paddle API Exception (unknown result)', [
            'operation_uuid' => $operation->operation_uuid,
            'type' => $operation->type->value,
            'plan' => $plan,
            'exception_class' => $e::class,
            'exception_code' => $e->getCode(),
        ]);

        $updated = SubscriptionOperation::query()
            ->where('id', $operation->id)
            ->where('claim_token', $operation->claim_token)
            ->where('status', SubscriptionOperationStatus::Processing)
            ->update([
                'status' => SubscriptionOperationStatus::Unknown,
                'available_at' => now()->addMinute(),
                'last_attempt_at' => now(),
                'last_error_class' => $e::class,
                'last_error_code' => (string) $e->getCode(),
            ]);

        if ($updated === 0) {
            return $this->returnExistingOperation($operation->fresh(), $operation->type, $plan);
        }

        return new SubscriptionOperationResult(
            operation: SubscriptionOperation::find($operation->id),
            message: 'Your request is being processed. We will confirm the result shortly.',
        );
    }

    private function validateSwapAllowed(User $user, string $plan): void
    {
        if (! $user->isBillingSubscribed()) {
            throw new SubscriptionException(
                'You are not subscribed to a paid plan.',
                action: 'swap',
                plan: $plan,
                currentState: $user->subscription($user->subscriptionName())?->paddle_status
            );
        }

        if ($user->activeBillingPlan() === $plan) {
            throw new SubscriptionException(
                'You are already on this plan.',
                action: 'swap',
                plan: $plan,
                currentState: $user->subscription($user->subscriptionName())?->paddle_status
            );
        }
    }

    private function validateCancelAllowed(User $user, string $plan): void
    {
        if (! $user->isBillingSubscribed()) {
            // No-op cancel - already canceled or never subscribed
            throw new SubscriptionException(
                'You are not subscribed to a paid plan.',
                action: 'cancel',
                plan: $plan,
                currentState: $user->subscription($user->subscriptionName())?->paddle_status
            );
        }

        if ($user->activeBillingPlan() !== $plan) {
            throw new SubscriptionException(
                'You are not subscribed to this plan.',
                action: 'cancel',
                plan: $plan,
                currentState: $user->subscription($user->subscriptionName())?->paddle_status
            );
        }
    }

    private function resolvePlanId(string $plan, string $action): int
    {
        $planId = config("services.paddle.{$plan}");

        if (blank($planId) || ! is_numeric($planId)) {
            throw new SubscriptionException(
                "The {$plan} plan is not configured. Please contact support.",
                action: $action,
                plan: $plan
            );
        }

        return (int) $planId;
    }

    private function getMessageForStatus(SubscriptionOperationStatus $status, SubscriptionOperationType $type, string $plan): string
    {
        return match ($status) {
            SubscriptionOperationStatus::Completed => $type === SubscriptionOperationType::Swap
                ? "Your subscription has been successfully updated to the {$plan} plan"
                : 'Your subscription has been canceled successfully.',
            SubscriptionOperationStatus::Failed => $type === SubscriptionOperationType::Swap
                ? 'Your subscription change could not be completed. Please try again or contact support.'
                : 'Your subscription cancellation could not be completed. Please try again or contact support.',
            SubscriptionOperationStatus::Processing,
            SubscriptionOperationStatus::Unknown,
            SubscriptionOperationStatus::Pending => 'Your request is being processed. We will confirm the result shortly.',
            default => 'Your request is being processed.',
        };
    }

    private function validateSubscribeAllowed(User $user, string $plan): void
    {
        if (! $user->isSubscribed()) {
            return;
        }

        if ($user->isBillingSubscribed()) {
            $currentPlan = $user->activeBillingPlan();

            throw new SubscriptionException(
                $currentPlan === $plan
                    ? 'You are already subscribed to this plan.'
                    : 'You already have an active paid plan. Please swap plans instead.',
                action: 'subscribe',
                plan: $plan,
                currentState: $user->subscription($user->subscriptionName())?->paddle_status
            );
        }

        throw new SubscriptionException(
            'You have an existing subscription. Please resume or swap your subscription instead.',
            action: 'subscribe',
            plan: $plan,
            currentState: $user->subscription($user->subscriptionName())?->paddle_status
        );
    }
}
