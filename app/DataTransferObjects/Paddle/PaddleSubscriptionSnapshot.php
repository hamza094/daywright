<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Paddle;

final readonly class PaddleSubscriptionSnapshot
{
    public function __construct(
        public string $subscription_id,
        public string $plan_id,
        public string $status,
        public ?string $cancellation_effective_date = null,
    ) {}

    /** @param array<string, mixed> $response */
    public static function fromPaddleResponse(array $response): self
    {
        return new self(
            subscription_id: (string) ($response['subscription_id'] ?? ''),
            plan_id: (string) ($response['plan_id'] ?? ''),
            status: (string) ($response['state'] ?? ''),
            cancellation_effective_date: isset($response['cancellation_effective_date'])
                ? (string) $response['cancellation_effective_date']
                : null,
        );
    }

    /**
     * Check if this snapshot proves a swap to the target plan succeeded
     */
    public function provesSwapSucceeded(string $targetPlanId): bool
    {
        return $this->plan_id === $targetPlanId && $this->status === 'active';
    }

    /**
     * Check if this snapshot proves a cancel succeeded
     */
    public function provesCancelSucceeded(): bool
    {
        return $this->status === 'deleted'
            && $this->cancellation_effective_date !== null
            && trim($this->cancellation_effective_date) !== '';
    }
}
