<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Paddle;

final readonly class PaddleSubscriptionSnapshot
{
    public function __construct(
        public string $subscriptionId,
        public string $planId,
        public string $status,
        public ?string $cancellationEffectiveDate = null,
    ) {}

    /** @param array<string, mixed> $response */
    public static function fromPaddleResponse(array $response): self
    {
        return new self(
            subscriptionId: (string) ($response['subscription_id'] ?? ''),
            planId: (string) ($response['plan_id'] ?? ''),
            status: (string) ($response['state'] ?? ''),
            cancellationEffectiveDate: isset($response['cancellation_effective_date'])
                ? (string) $response['cancellation_effective_date']
                : null,
        );
    }

    /**
     * Check if this snapshot proves a swap to the target plan succeeded
     */
    public function provesSwapSucceeded(string $targetPlanId): bool
    {
        return $this->planId === $targetPlanId && $this->status === 'active';
    }

    /**
     * Check if this snapshot proves a cancel succeeded
     */
    public function provesCancelSucceeded(): bool
    {
        return $this->status === 'deleted'
            && $this->cancellationEffectiveDate !== null
            && trim($this->cancellationEffectiveDate) !== '';
    }
}
