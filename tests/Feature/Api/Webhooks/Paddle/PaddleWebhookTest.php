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
            'alert_id' => 'evt_abc123',
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
        $this->assertSame('paddle', $log->metadata['provider']);
        $this->assertSame('evt_abc123', $log->metadata['provider_event_id']);
        $this->assertArrayNotHasKey('user_email', $log->new_values);
        $this->assertArrayNotHasKey('alert_name', $log->new_values);
        $this->assertArrayNotHasKey('paddle_payload', $log->metadata);
        $this->assertNotNull($log->created_at);
    }

    #[Test]
    public function subscription_updated_webhook_creates_audit_log(): void
    {
        Config::set('cashier.public_key');

        $this->postJson('/paddle/webhook', [
            'alert_name' => 'subscription_updated',
            'subscription_id' => 'sub_789',
            'alert_id' => 'evt_def456',
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
        $this->assertSame('paddle', $log->metadata['provider']);
        $this->assertSame('evt_def456', $log->metadata['provider_event_id']);
        $this->assertArrayNotHasKey('user_email', $log->new_values);
        $this->assertArrayNotHasKey('alert_name', $log->new_values);
        $this->assertArrayNotHasKey('paddle_payload', $log->metadata);
        $this->assertNotNull($log->created_at);
    }

    #[Test]
    public function subscription_cancelled_webhook_creates_audit_log(): void
    {
        Config::set('cashier.public_key');

        $this->postJson('/paddle/webhook', [
            'alert_name' => 'subscription_cancelled',
            'subscription_id' => 'sub_789',
            'alert_id' => 'evt_ghi789',
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
        $this->assertSame('paddle', $log->metadata['provider']);
        $this->assertSame('evt_ghi789', $log->metadata['provider_event_id']);
        $this->assertArrayNotHasKey('user_email', $log->new_values);
        $this->assertArrayNotHasKey('alert_name', $log->new_values);
        $this->assertArrayNotHasKey('paddle_payload', $log->metadata);
        $this->assertNotNull($log->created_at);
    }

    #[Test]
    public function webhook_with_sensitive_data_sanitizes_before_audit(): void
    {
        Config::set('cashier.public_key');

        $this->postJson('/paddle/webhook', [
            'alert_name' => 'subscription_created',
            'subscription_id' => 'sub_secret123',
            'alert_id' => 'evt_secret_abc',
            'email' => 'secret-user@example.com',
            'customer_auth_code' => 'secret-auth-code',
            'passthrough' => 'secret-passthrough-data',
        ])->assertStatus(200);

        $log = AuditLog::where('event', 'billing.subscription_created')->first();

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('user_email', $log->new_values);
        $this->assertArrayNotHasKey('paddle_payload', $log->metadata);
        $this->assertStringNotContainsString('secret-auth-code', json_encode($log->new_values));
        $this->assertStringNotContainsString('secret-passthrough-data', json_encode($log->metadata));
    }
}
