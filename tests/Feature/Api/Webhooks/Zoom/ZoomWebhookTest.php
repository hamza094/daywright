<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Webhooks\Zoom;

use App\Jobs\Webhooks\Zoom\DeleteMeetingWebhook;
use App\Jobs\Webhooks\Zoom\MeetingEndedWebhook;
use App\Jobs\Webhooks\Zoom\StartMeetingWebhook;
use App\Jobs\Webhooks\Zoom\UpdateMeetingWebhook;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Override;
use Tests\Support\Zoom\ZoomWebhookSigner;
use Tests\TestCase;

class ZoomWebhookTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Clear replay and idempotency cache between tests
        Cache::flush();

        config(['services.zoom.webhook_secret' => 'secret']);

        $this->travelTo(Carbon::parse('2024-06-24 11:49:48'));

        Queue::fake([
            UpdateMeetingWebhook::class,
            DeleteMeetingWebhook::class,
            StartMeetingWebhook::class,
            MeetingEndedWebhook::class,
        ]);

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

        $object = $postBody['payload']['object'];
        $meetingId = $object['id'];

        $requestId = 'zoom-update-'.Str::uuid();

        $this->postJson(route('api.v1.webhooks.meetings.update'), $postBody, ZoomWebhookSigner::signPayload($postBody, $requestId))
            ->assertOk()
            ->assertExactJson(['message' => 'Webhook accepted.']);

        Queue::assertPushed(UpdateMeetingWebhook::class, fn ($job): bool => $job->getMeetingId() === (int) $meetingId
            && $job->data->requestId === $requestId);
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

        $object = $postBody['payload']['object'];
        $meetingId = $object['id'];

        $this->postJson(route('api.v1.webhooks.meetings.delete'), $postBody, ZoomWebhookSigner::signPayload($postBody, 'zoom-delete-813'))
            ->assertOk()
            ->assertExactJson(['message' => 'Webhook accepted.']);

        Queue::assertPushed(DeleteMeetingWebhook::class, fn ($job): bool => $job->getMeetingId() === $meetingId
            && $job->data->requestId === 'zoom-delete-813');
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

        $object = $postBody['payload']['object'];
        $meetingId = $object['id'];

        $this->postJson(route('api.v1.webhooks.meetings.start'), $postBody, ZoomWebhookSigner::signPayload($postBody, 'zoom-start-813'))
            ->assertOk()
            ->assertExactJson(['message' => 'Webhook accepted.']);

        Queue::assertPushed(StartMeetingWebhook::class, fn ($job): bool => $job->getMeetingId() === (int) $meetingId
            && $job->data->requestId === 'zoom-start-813');
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

        $object = $postBody['payload']['object'];
        $meetingId = $object['id'];

        $this->postJson(route('api.v1.webhooks.meetings.ended'), $postBody, ZoomWebhookSigner::signPayload($postBody, 'zoom-ended-813'))
            ->assertOk()
            ->assertExactJson(['message' => 'Webhook accepted.']);

        Queue::assertPushed(MeetingEndedWebhook::class, fn ($job): bool => $job->getMeetingId() === (int) $meetingId
            && $job->data->requestId === 'zoom-ended-813');

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
    public function duplicate_webhook_request_with_same_request_id_does_not_dispatch_job_twice(): void
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

        $this->postJson(route('api.v1.webhooks.meetings.update'), $postBody, ZoomWebhookSigner::signPayload($postBody, $requestId))
            ->assertOk()
            ->assertExactJson(['message' => 'Webhook accepted.']);

        Queue::assertPushed(UpdateMeetingWebhook::class, 1);

        // Send the same request again with the same request ID
        $this->postJson(route('api.v1.webhooks.meetings.update'), $postBody, ZoomWebhookSigner::signPayload($postBody, $requestId))
            ->assertStatus(202)
            ->assertExactJson(['message' => 'Webhook accepted']);

        // Job should still only be pushed once due to idempotency
        Queue::assertPushed(UpdateMeetingWebhook::class, 1);
    }

    /** @test */
    public function different_request_id_with_same_body_is_treated_as_replay(): void
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

        $requestId1 = 'zoom-update-1';
        $requestId2 = 'zoom-update-2';

        $this->postJson(route('api.v1.webhooks.meetings.update'), $postBody, ZoomWebhookSigner::signPayload($postBody, $requestId1))
            ->assertOk();

        // Same body/timestamp/signature with different request ID is treated as replay
        $this->postJson(route('api.v1.webhooks.meetings.update'), $postBody, ZoomWebhookSigner::signPayload($postBody, $requestId2))
            ->assertStatus(202)
            ->assertExactJson(['message' => 'Webhook accepted']);

        // Only one job should be pushed due to replay protection
        Queue::assertPushed(UpdateMeetingWebhook::class, 1);
    }
}
