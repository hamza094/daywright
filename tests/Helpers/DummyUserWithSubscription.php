<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Traits\HasSubscription;

final class DummyUserWithSubscription
{
    use HasSubscription {
        resolveBillingPlanName as public;
    }

    private const SUBSCRIPTION_NAME = 'DayWright';

    public function __construct(
        private readonly ?object $subscription = null,
        private readonly bool $genericTrial = false,
        private readonly bool $namedTrial = false,
    ) {}

    public function subscriptionName(): string
    {
        return self::SUBSCRIPTION_NAME;
    }

    public function subscription(string $name): ?object
    {
        return $this->subscription;
    }

    public function onTrial(?string $name = null): bool
    {
        return $name === null ? $this->genericTrial : $this->namedTrial;
    }
}
