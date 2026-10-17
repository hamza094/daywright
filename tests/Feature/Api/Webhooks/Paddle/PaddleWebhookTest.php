<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Webhooks\Paddle;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Laravel\Paddle\Cashier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PaddleWebhookTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cashier_webhook_route_is_registered(): void
    {
        $this->assertTrue(Route::has('cashier.webhook'));
    }

    #[Test]
    public function cashier_uses_configured_webhook_url(): void
    {
        Config::set('cashier.webhook', 'https://api.example.test/paddle/webhook');

        $this->assertSame(
            'https://api.example.test/paddle/webhook',
            Cashier::webhookUrl(),
        );
    }
}
