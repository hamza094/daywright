<?php

declare(strict_types=1);

namespace App\Exceptions\Subscription;

use RuntimeException;

final class SubscriptionRequiredException extends RuntimeException
{
    public function __construct(
        string $message = 'Access denied. An active subscription is required to perform this action.',
        private readonly bool $upgradeRequired = true,
    ) {
        parent::__construct($message);
    }

    public function errorType(): string
    {
        return 'subscription_required';
    }

    public function upgradeRequired(): bool
    {
        return $this->upgradeRequired;
    }
}
