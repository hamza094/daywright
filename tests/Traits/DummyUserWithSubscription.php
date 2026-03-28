<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Traits\HasSubscription;

class DummyUserWithSubscription
{
    use HasSubscription {
        resolveBillingPlanName as public;
    }

    public mixed $mockSubscription;

    // Provide a stable subscription name for unit tests (avoids calling the global config() helper)
    public function subscriptionName(): string
    {
        return 'DayWright';
    }

    public function subscription(string $name): mixed
    {
        return $this->mockSubscription;
    }
}
