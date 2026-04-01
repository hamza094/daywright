<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Traits\HasSubscription;

final readonly class DummyUserWithSubscription
{
    use HasSubscription {
        resolveBillingPlanName as public;
    }

    private const string SUBSCRIPTION_NAME = 'DayWright';

    public function __construct(
        private ?object $subscription = null,
        private bool $genericTrial = false,
        private bool $namedTrial = false,
    ) {}

    public function subscriptionName(): string
    {
        return self::SUBSCRIPTION_NAME;
    }

    public function subscription(): ?object
    {
        return $this->subscription;
    }

    public function onTrial(?string $name = null): bool
    {
        return $name === null ? $this->genericTrial : $this->namedTrial;
    }
}
