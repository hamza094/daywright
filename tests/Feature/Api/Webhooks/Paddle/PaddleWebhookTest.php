<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Webhooks\Paddle;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PaddleWebhookTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function paddle_webhook_route_exists(): void
    {
        $this->assertTrue(Route::has('cashier.webhook'));
    }

    #[Test]
    public function it_accepts_webhook_without_signature_when_public_key_not_configured(): void
    {
        Config::set('cashier.public_key', null);

        $response = $this->postJson('/paddle/webhook', [
            'alert_name' => 'subscription_created',
            'subscription_id' => 123,
        ]);

        $response->assertStatus(200);
    }
}
