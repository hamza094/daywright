<?php

declare(strict_types=1);

namespace App\Exceptions\Subscription;

use RuntimeException;

final class PlanLimitExceededException extends RuntimeException
{
    public const string REASON_LIMIT_REACHED = 'limit_reached';

    public const string REASON_TRIAL_EXPIRED = 'trial_expired';

    public const string SCOPE_ACCOUNT = 'account';

    public const string SCOPE_PROJECT = 'project';

    public function __construct(
        string $message,
        private readonly string $limitType,
        private readonly string $reason,
        private readonly int $currentUsage,
        private readonly ?int $maxAllowed,
        private readonly string $limitScope,
        private readonly int $limitOwnerId,
    ) {
        parent::__construct($message);
    }

    public function limitType(): string
    {
        return $this->limitType;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function currentUsage(): int
    {
        return $this->currentUsage;
    }

    public function maxAllowed(): ?int
    {
        return $this->maxAllowed;
    }

    public function limitScope(): string
    {
        return $this->limitScope;
    }

    public function limitOwnerId(): int
    {
        return $this->limitOwnerId;
    }
}
