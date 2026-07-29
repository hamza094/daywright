<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Webhooks\Paddle;

use App\Models\AuditLog;
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
        Config::set('cashier.public_key');

        $response = $this->postJson('/paddle/webhook', [
            'alert_name' => 'subscription_created',
            'subscription_id' => 123,
        ]);

        $response->assertStatus(200);
    }

    #[Test]
    public function subscription_created_webhook_creates_audit_log(): void
    {
        Config::set('cashier.public_key');

        $this->postJson('/paddle/webhook', [
            'alert_name' => 'subscription_created',
            'subscription_id' => 'sub_123',
            'email' => 'user@example.com',
        ])->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'system',
            'actor_id' => null,
            'event' => 'billing.subscription_created',
        ]);

        $log = AuditLog::where('event', 'billing.subscription_created')->first();

        $this->assertNotNull($log);
        $this->assertSame('subscription_created', $log->new_values['paddle_event']);
        $this->assertSame('sub_123', $log->new_values['subscription_id']);
        $this->assertSame('user@example.com', $log->new_values['user_email']);
        $this->assertNotNull($log->metadata['paddle_payload']);
        $this->assertNotNull($log->created_at);
    }

    #[Test]
    public function subscription_updated_webhook_creates_audit_log(): void
    {
        Config::set('cashier.public_key');

        $this->postJson('/paddle/webhook', [
            'alert_name' => 'subscription_updated',
            'subscription_id' => 'sub_789',
            'email' => 'user@example.com',
        ])->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'system',
            'actor_id' => null,
            'event' => 'billing.subscription_updated',
        ]);

        $log = AuditLog::where('event', 'billing.subscription_updated')->first();

        $this->assertNotNull($log);
        $this->assertSame('subscription_updated', $log->new_values['paddle_event']);
        $this->assertSame('sub_789', $log->new_values['subscription_id']);
        $this->assertSame('user@example.com', $log->new_values['user_email']);
        $this->assertNotNull($log->metadata['paddle_payload']);
        $this->assertNotNull($log->created_at);
    }

    #[Test]
    public function subscription_cancelled_webhook_creates_audit_log(): void
    {
        Config::set('cashier.public_key');

        $this->postJson('/paddle/webhook', [
            'alert_name' => 'subscription_cancelled',
            'subscription_id' => 'sub_789',
            'email' => 'user@example.com',
        ])->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'system',
            'actor_id' => null,
            'event' => 'billing.subscription_cancelled',
        ]);

        $log = AuditLog::where('event', 'billing.subscription_cancelled')->first();

        $this->assertNotNull($log);
        $this->assertSame('subscription_cancelled', $log->new_values['paddle_event']);
        $this->assertSame('sub_789', $log->new_values['subscription_id']);
        $this->assertSame('user@example.com', $log->new_values['user_email']);
        $this->assertNotNull($log->metadata['paddle_payload']);
        $this->assertNotNull($log->created_at);
    }
}
