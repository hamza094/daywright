<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Webhooks\Zoom;

use App\Enums\WebhookInboxState;
use App\Models\WebhookInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebhookInboxTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function schema_constraints_and_casts_work(): void
    {
        $inbox = WebhookInbox::factory()->create([
            'provider' => 'zoom',
            'event_key' => 'test-event-key-123',
            'event_type' => 'meeting.updated',
            'provider_request_id' => 'req-123',
            'provider_occurred_at' => 1234567890000,
            'payload' => [
                'meetingId' => 123456789,
                'changes' => ['topic' => 'Test Meeting'],
            ],
            'state' => WebhookInboxState::Received,
            'attempts' => 0,
        ]);

        $this->assertEquals('zoom', $inbox->provider);
        $this->assertEquals('test-event-key-123', $inbox->event_key);
        $this->assertEquals('meeting.updated', $inbox->event_type);
        $this->assertEquals('req-123', $inbox->provider_request_id);
        $this->assertEquals(1234567890000, $inbox->provider_occurred_at);
        $this->assertEquals(WebhookInboxState::Received, $inbox->state);
        $this->assertEquals(0, $inbox->attempts);
        $this->assertIsArray($inbox->payload);
        $this->assertEquals(123456789, $inbox->payload['meetingId']);
    }

    #[Test]
    public function duplicate_provider_event_key_is_rejected(): void
    {
        WebhookInbox::factory()->create([
            'provider' => 'zoom',
            'event_key' => 'duplicate-key',
            'event_type' => 'meeting.updated',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        WebhookInbox::factory()->create([
            'provider' => 'zoom',
            'event_key' => 'duplicate-key',
            'event_type' => 'meeting.deleted',
        ]);
    }

    #[Test]
    public function same_key_can_exist_for_different_provider(): void
    {
        WebhookInbox::factory()->create([
            'provider' => 'zoom',
            'event_key' => 'shared-key',
            'event_type' => 'meeting.updated',
        ]);

        WebhookInbox::factory()->create([
            'provider' => 'paddle',
            'event_key' => 'shared-key',
            'event_type' => 'subscription.activated',
        ]);

        $this->assertDatabaseCount('webhook_inboxes', 2);
    }

    #[Test]
    public function raw_database_payload_is_encrypted(): void
    {
        $inbox = WebhookInbox::factory()->create([
            'payload' => [
                'meetingId' => 123456789,
                'password' => 'secret-password',
                'join_url' => 'https://zoom.us/j/123456?pwd=secret',
                'start_url' => 'https://zoom.us/s/123456?pwd=secret',
            ],
        ]);

        $rawPayload = DB::table('webhook_inboxes')
            ->where('id', $inbox->id)
            ->value('payload');

        $this->assertIsString($rawPayload);
        $this->assertStringNotContainsString('secret-password', $rawPayload);
        $this->assertStringNotContainsString('pwd=secret', $rawPayload);
    }

    #[Test]
    public function state_enum_cast_works_correctly(): void
    {
        $inbox = WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Processing,
        ]);

        $this->assertEquals(WebhookInboxState::Processing, $inbox->state);
        $this->assertInstanceOf(WebhookInboxState::class, $inbox->state);
    }

    #[Test]
    public function hidden_fields_are_not_visible_in_array(): void
    {
        $inbox = WebhookInbox::factory()->create([
            'payload' => ['test' => 'data'],
            'claim_token' => 'test-token',
        ]);

        $array = $inbox->toArray();

        $this->assertArrayNotHasKey('payload', $array);
        $this->assertArrayNotHasKey('claim_token', $array);
    }
}
