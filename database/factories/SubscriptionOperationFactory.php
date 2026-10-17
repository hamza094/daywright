<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Subscription\SubscriptionOperationStatus;
use App\Enums\Subscription\SubscriptionOperationType;
use App\Models\SubscriptionOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionOperation>
 *
 * @method SubscriptionOperation create(array<string, mixed> $attributes = [])
 * @method SubscriptionOperation make(array<string, mixed> $attributes = [])
 */
final class SubscriptionOperationFactory extends Factory
{
    protected $model = SubscriptionOperation::class;

    /**
     * @return array{
     *     operation_uuid: string,
     *     user_id: Factory<User>|int,
     *     subscription_id: null,
     *     paddle_subscription_id: string,
     *     type: SubscriptionOperationType,
     *     target_plan: string|null,
     *     idempotency_key_hash: string,
     *     request_fingerprint: string,
     *     status: SubscriptionOperationStatus,
     *     attempts: int,
     *     available_at: null,
     *     claim_token: null,
     *     claimed_at: null,
     *     claim_expires_at: null,
     *     provider_attempted_at: null,
     *     completed_at: null,
     *     failed_at: null,
     *     last_attempt_at: null,
     *     last_error_class: null,
     *     last_error_code: null,
     * }
     */
    public function definition(): array
    {
        return [
            'operation_uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'subscription_id' => null,
            'paddle_subscription_id' => (string) $this->faker->numberBetween(100000, 999999),
            'type' => SubscriptionOperationType::Swap,
            'target_plan' => 'pro',
            'idempotency_key_hash' => hash('sha256', (string) Str::uuid()),
            'request_fingerprint' => hash('sha256', 'fingerprint-sample'),
            'status' => SubscriptionOperationStatus::Pending,
            'attempts' => 0,
            'available_at' => null,
            'claim_token' => null,
            'claimed_at' => null,
            'claim_expires_at' => null,
            'provider_attempted_at' => null,
            'completed_at' => null,
            'failed_at' => null,
            'last_attempt_at' => null,
            'last_error_class' => null,
            'last_error_code' => null,
        ];
    }

    public function swap(?string $targetPlan = 'pro'): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => SubscriptionOperationType::Swap,
            'target_plan' => $targetPlan,
        ]);
    }

    public function cancel(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => SubscriptionOperationType::Cancel,
            'target_plan' => null,
        ]);
    }

    public function processing(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionOperationStatus::Processing,
            'claim_token' => (string) Str::uuid(),
            'claimed_at' => now(),
            'claim_expires_at' => now()->addMinutes(5),
            'attempts' => 1,
            'last_attempt_at' => now(),
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionOperationStatus::Completed,
            'completed_at' => now(),
            'provider_attempted_at' => now(),
        ]);
    }

    public function failed(?string $errorClass = 'App\\Exceptions\\Paddle\\PaddleException', ?string $errorCode = 'payment_declined'): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionOperationStatus::Failed,
            'failed_at' => now(),
            'provider_attempted_at' => now(),
            'last_error_class' => $errorClass,
            'last_error_code' => $errorCode,
        ]);
    }

    public function unknown(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionOperationStatus::Unknown,
            'provider_attempted_at' => now(),
            'available_at' => now()->subSecond(),
        ]);
    }

    public function manualReview(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionOperationStatus::ManualReview,
            'provider_attempted_at' => now(),
        ]);
    }
}
