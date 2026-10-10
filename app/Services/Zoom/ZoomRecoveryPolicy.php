<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\Models\Meeting;
use Carbon\CarbonInterface;

final readonly class ZoomRecoveryPolicy
{
    private const int MAX_ATTEMPTS = 5;

    private const array BACKOFF_SECONDS = [
        0 => 60,
        1 => 300,
        2 => 900,
        3 => 3600,
    ];

    public function maxAttempts(): int
    {
        return self::MAX_ATTEMPTS;
    }

    public function getBackoffSeconds(int $attempt): int
    {
        return self::BACKOFF_SECONDS[$attempt] ?? 3600;
    }

    public function isClaimValid(Meeting $meeting, string $expectedClaimToken): bool
    {
        return $meeting->sync_claim_token === $expectedClaimToken
            && $meeting->sync_lease_expires_at !== null
            && $meeting->sync_lease_expires_at->isFuture();
    }

    public function isOperationCurrent(Meeting $meeting, string $expectedOperationId): bool
    {
        return $meeting->sync_operation_id === $expectedOperationId;
    }

    public function shouldRetry(int $currentAttempts): bool
    {
        return $currentAttempts < self::MAX_ATTEMPTS;
    }

    public function shouldSkipExpiredLease(Meeting $meeting, CarbonInterface $now): bool
    {
        return $meeting->sync_lease_expires_at !== null
            && $meeting->sync_lease_expires_at->isPast();
    }

    /**
     * @return array<string, null>
     */
    public function getClaimState(): array
    {
        return [
            'sync_claim_token' => null,
            'sync_lease_expires_at' => null,
            'sync_available_at' => null,
        ];
    }
}
