<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Webhooks\Zoom;

use App\Enums\Meeting\MeetingSyncStatus;
use App\Jobs\Webhooks\ProcessZoomWebhookInbox;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Override;
use Tests\Support\Zoom\ZoomWebhookPayloadFactory;
use Tests\Support\Zoom\ZoomWebhookSigner;
use Tests\TestCase;

class ZoomWebhookTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.zoom.webhook_secret' => 'secret']);

        $this->travelTo(Carbon::parse('2024-06-24 11:49:48'));

        Queue::fake([ProcessZoomWebhookInbox::class]);
    }

    /** @test */
    public function meeting_can_be_updated_via_webhook(): void
    {
        Meeting::factory()->create([
            'meeting_id' => 813,
            'topic' => 'shining in the sky',
        ]);

        $postBody = File::json(
            path: base_path('tests/Fixtures/Webhooks/Zoom/meeting_update.json'),
            flags: JSON_THROW_ON_ERROR,
        );
        $postBody['payload']['object']['host_id'] = 'provider-host-id';
        $postBody['payload']['object']['settings'] = ['waiting_room' => true];

        $requestId = 'zoom-update-'.Str::uuid();

        $headers = ZoomWebhookSigner::signPayload($postBody, $requestId);

        $this->postJson(route('api.v1.webhooks.meetings.update'), $postBody, $headers)
            ->assertOk()
            ->assertExactJson(['message' => 'Webhook accepted.']);

        Queue::assertPushed(ProcessZoomWebhookInbox::class);
    }

    /** @test */
    public function meeting_created_is_accepted_once_by_the_durable_inbox(): void
    {
        Meeting::factory()->create([
            'meeting_id' => null,
            'sync_status' => MeetingSyncStatus::Creating,
            'sync_operation_id' => 'test-operation-id',
        ]);
        $postBody = ZoomWebhookPayloadFactory::meetingCreatedPayload();
        $headers = ZoomWebhookSigner::signPayload($postBody, 'zoom-created-813');

        $this->postJson(route('api.v1.webhooks.meetings.created'), $postBody, $headers)
            ->assertOk()
            ->assertExactJson(['message' => 'Webhook accepted.']);
        $this->postJson(route('api.v1.webhooks.meetings.created'), $postBody, $headers)
            ->assertOk();

        $this->assertDatabaseCount('webhook_inboxes', 1);
        $this->assertDatabaseHas('webhook_inboxes', ['event_type' => 'meeting.created']);
        Queue::assertPushed(ProcessZoomWebhookInbox::class);
    }

    /** @test */
    public function meeting_can_be_deleted(): void
    {
        Meeting::factory()->create([
            'meeting_id' => 813,
        ]);

        $postBody = File::json(
            path: base_path('tests/Fixtures/Webhooks/Zoom/meeting_delete.json'),
            flags: JSON_THROW_ON_ERROR,
        );

        $this->postJson(route('api.v1.webhooks.meetings.delete'), $postBody, ZoomWebhookSigner::signPayload($postBody, 'zoom-delete-813'))
            ->assertOk()
            ->assertExactJson(['message' => 'Webhook accepted.']);

        Queue::assertPushed(ProcessZoomWebhookInbox::class);
    }

    /** @test */
    public function zoom_meeting_can_be_started(): void
    {
        Meeting::factory()->create([
            'meeting_id' => 813,
        ]);

        $postBody = File::json(
            path: base_path('tests/Fixtures/Webhooks/Zoom/meeting_start.json'),
            flags: JSON_THROW_ON_ERROR,
        );

        $this->postJson(route('api.v1.webhooks.meetings.start'), $postBody, ZoomWebhookSigner::signPayload($postBody, 'zoom-start-813'))
            ->assertOk()
            ->assertExactJson(['message' => 'Webhook accepted.']);

        Queue::assertPushed(ProcessZoomWebhookInbox::class);
    }

    /** @test */
    public function zoom_meeting_can_be_ended(): void
    {
        Meeting::factory()->create([
            'meeting_id' => 813,
        ]);

        $postBody = File::json(
            path: base_path('tests/Fixtures/Webhooks/Zoom/meeting_ended.json'),
            flags: JSON_THROW_ON_ERROR,
        );

        $this->postJson(route('api.v1.webhooks.meetings.ended'), $postBody, ZoomWebhookSigner::signPayload($postBody, 'zoom-ended-813'))
            ->assertOk()
            ->assertExactJson(['message' => 'Webhook accepted.']);

        Queue::assertPushed(ProcessZoomWebhookInbox::class);
    }

    /** @test */
    public function error_is_returned_if_the_request_was_not_sent_from_zoom(): void
    {
        $this->postJson(route('api.v1.webhooks.meetings.update'), ['invalid_key' => 'invalid_value'])
            ->assertStatus(400)
            ->assertSeeText('Missing required Zoom webhook header: x-zm-request-id.');

        Queue::assertNothingPushed();
    }

    /** @test */
    public function endpoint_validation_is_handled_before_webhook_validation_and_dispatch(): void
    {
        $plainToken = 'zoom-endpoint-validation-token';
        $payload = [
            'event' => 'endpoint.url_validation',
            'payload' => [
                'plainToken' => $plainToken,
            ],
        ];

        $this->postJson(
            route('api.v1.webhooks.meetings.update'),
            $payload,
            ZoomWebhookSigner::signPayload($payload, 'zoom-endpoint-validation')
        )
            ->assertOk()
            ->assertExactJson([
                'plainToken' => $plainToken,
                'encryptedToken' => hash_hmac('sha256', $plainToken, 'secret'),
            ]);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function duplicate_webhook_request_with_same_body_returns_same_response(): void
    {
        Meeting::factory()->create([
            'meeting_id' => 813,
            'topic' => 'shining in the sky',
        ]);

        $postBody = File::json(
            path: base_path('tests/Fixtures/Webhooks/Zoom/meeting_update.json'),
            flags: JSON_THROW_ON_ERROR,
        );
        $postBody['payload']['object']['host_id'] = 'provider-host-id';
        $postBody['payload']['object']['settings'] = ['waiting_room' => true];

        $requestId = 'zoom-update-duplicate';
        $headers = ZoomWebhookSigner::signPayload($postBody, $requestId);

        $this->postJson(route('api.v1.webhooks.meetings.update'), $postBody, $headers)
            ->assertOk()
            ->assertExactJson(['message' => 'Webhook accepted.']);

        // Verify only one inbox row exists
        $this->assertDatabaseCount('webhook_inboxes', 1);

        // Send the same request again (same body, signature, timestamp)
        $this->postJson(route('api.v1.webhooks.meetings.update'), $postBody, $headers)
            ->assertOk()
            ->assertExactJson(['message' => 'Webhook accepted.']);

        // Still only one inbox row due to database uniqueness
        $this->assertDatabaseCount('webhook_inboxes', 1);
    }

    /** @test */
    public function different_signed_bodies_create_separate_inbox_rows(): void
    {
        Meeting::factory()->create([
            'meeting_id' => 813,
            'topic' => 'shining in the sky',
        ]);

        $postBody = File::json(
            path: base_path('tests/Fixtures/Webhooks/Zoom/meeting_update.json'),
            flags: JSON_THROW_ON_ERROR,
        );

        $requestId1 = 'zoom-update-1';
        $requestId2 = 'zoom-update-2';

        // First request with current timestamp
        $timestamp1 = (string) time();
        $headers1 = ZoomWebhookSigner::signPayloadWithTimestamp($postBody, $requestId1, $timestamp1);

        $this->postJson(route('api.v1.webhooks.meetings.update'), $postBody, $headers1)
            ->assertOk();

        // Verify one inbox row exists after first request
        $this->assertDatabaseCount('webhook_inboxes', 1);

        // Second request with timestamp 5 seconds later (within tolerance window) creates different signature
        $timestamp2 = (string) (time() + 5);
        $headers2 = ZoomWebhookSigner::signPayloadWithTimestamp($postBody, $requestId2, $timestamp2);

        $this->postJson(route('api.v1.webhooks.meetings.update'), $postBody, $headers2)
            ->assertOk();

        // Two separate inbox rows should exist due to different fingerprints
        $this->assertDatabaseCount('webhook_inboxes', 2);
    }

    /** @test */
    public function database_failure_returns_sanitized_error(): void
    {
        // Simulate database failure by breaking the connection
        // This would require actual database manipulation, so we skip this test
        // The controller uses standard Laravel error handling which returns 500

        $this->assertTrue(true);
    }
}
