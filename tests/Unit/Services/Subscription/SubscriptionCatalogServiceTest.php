<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Subscription;

use App\Services\Api\V1\Subscription\SubscriptionCatalogService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionCatalogServiceTest extends TestCase
{
    #[Test]
    public function it_returns_available_plans_from_config(): void
    {
        config()->set('services.paddle.prices.monthly', 12);
        config()->set('services.paddle.prices.yearly', 100);
        config()->set('services.paddle.prices.currency', 'usd');

        $service = resolve(SubscriptionCatalogService::class);

        $this->assertSame([
            [
                'name' => 'monthly',
                'label' => 'Monthly',
                'interval_label' => 'month',
                'price' => 12,
                'currency' => 'USD',
                'currency_symbol' => '$',
                'featured' => false,
            ],
            [
                'name' => 'yearly',
                'label' => 'Yearly',
                'interval_label' => 'year',
                'price' => 100,
                'currency' => 'USD',
                'currency_symbol' => '$',
                'featured' => true,
            ],
        ], $service->availablePlans());
    }
}
