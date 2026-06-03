<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Webhooks\Zoom;

use App\Jobs\Webhooks\Zoom\DeleteMeetingWebhook;
use App\Jobs\Webhooks\Zoom\MeetingEndsWebhook;
use App\Jobs\Webhooks\Zoom\StartMeetingWebhook;
use App\Jobs\Webhooks\Zoom\UpdateMeetingWebhook;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Override;
use Tests\TestCase;

use function Safe\json_encode;

class ZoomWebhookTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.zoom.webhook_secret' => 'secret']);

        $this->travelTo(Carbon::parse('2024-06-24 11:49:48'));

        Queue::fake([
            UpdateMeetingWebhook::class,
            DeleteMeetingWebhook::class,
            StartMeetingWebhook::class,
            MeetingEndsWebhook::class,
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
        $updateData = [
            'topic' => $object['topic'],
        ];

        $requestId = 'zoom-update-'.Str::uuid();

        $this->postJson(route('api.v1.webhooks.meetings.update'), $postBody, $this->zoomWebhookHeaders($postBody, $requestId))
            ->assertOk()
            ->assertExactJson(['message' => 'Webhook accepted.']);

        Queue::assertPushed(UpdateMeetingWebhook::class, fn ($job): bool => $job->meeting_id === (int) $meetingId
            && $job->update_data === $updateData
            && $job->request_id === $requestId);
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

        $this->postJson(route('api.v1.webhooks.meetings.delete'), $postBody, $this->zoomWebhookHeaders($postBody, 'zoom-delete-813'))
            ->assertOk()
            ->assertExactJson(['message' => 'Webhook accepted.']);

        Queue::assertPushed(DeleteMeetingWebhook::class, fn ($job): bool => $job->meeting_id === $meetingId
            && $job->request_id === 'zoom-delete-813');
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
        $startTime = $object['start_time'] ?? null;

        $this->postJson(route('api.v1.webhooks.meetings.start'), $postBody, $this->zoomWebhookHeaders($postBody, 'zoom-start-813'))
            ->assertOk()
            ->assertExactJson(['message' => 'Webhook accepted.']);

        Queue::assertPushed(StartMeetingWebhook::class, fn ($job): bool => (int) $job->meeting_id === (int) $meetingId
            && $job->start_time === $startTime
            && $job->request_id === 'zoom-start-813');
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
        $startTime = $object['start_time'] ?? null;
        $endTime = $object['end_time'] ?? null;

        $this->postJson(route('api.v1.webhooks.meetings.ended'), $postBody, $this->zoomWebhookHeaders($postBody, 'zoom-ended-813'))
            ->assertOk()
            ->assertExactJson(['message' => 'Webhook accepted.']);

        Queue::assertPushed(MeetingEndsWebhook::class, fn ($job): bool => (int) $job->meeting_id === (int) $meetingId
            && $job->start_time === $startTime
            && $job->end_time === $endTime
            && $job->request_id === 'zoom-ended-813');

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
            $this->zoomWebhookHeaders($payload, 'zoom-endpoint-validation')
        )
            ->assertOk()
            ->assertExactJson([
                'plainToken' => $plainToken,
                'encryptedToken' => hash_hmac('sha256', $plainToken, 'secret'),
            ]);

        Queue::assertNothingPushed();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function zoomWebhookHeaders(array $payload, string $requestId): array
    {
        $timestamp = (string) time();
        $rawPayload = json_encode($payload);

        return [
            'x-zm-request-timestamp' => $timestamp,
            'x-zm-signature' => $this->buildSignature($timestamp, $rawPayload),
            'x-zm-request-id' => $requestId,
        ];
    }

    private function buildSignature(string $timestamp, string $payload): string
    {
        $message = 'v0:'.$timestamp.':'.$payload;

        return 'v0='.hash_hmac('sha256', $message, (string) config('services.zoom.webhook_secret'));
    }
}
