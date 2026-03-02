<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Controllers\Webhooks;

use App\Jobs\Webhooks\Zoom\DeleteMeetingWebhook;
use App\Jobs\Webhooks\Zoom\MeetingEndsWebhook;
use App\Jobs\Webhooks\Zoom\StartMeetingWebhook;
use App\Jobs\Webhooks\Zoom\UpdateMeetingWebhook;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Override;
use Tests\TestCase;

class ZoomWebhookControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

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
        $this->withoutMiddleware([\App\Http\Middleware\VerifyZoomWebhook::class]);

        Meeting::factory()->create([
            'meeting_id' => 813,
            'topic' => 'shining in the sky',
        ]);

        $postBody = File::json(
            path: base_path('tests/Fixtures/Webhooks/Zoom/meeting_update.json'),
            flags: JSON_THROW_ON_ERROR,
        );

        $object = $postBody['payload']['object'];
        $meetingId = $object['id'];
        $updateData = collect($object)->except(['id', 'uuid'])->toArray();

        $this->post(route('webhooks.meetings.update'), $postBody)
            ->assertOk()
            ->assertExactJson(['status' => 'success']);

        Queue::assertPushed(UpdateMeetingWebhook::class, fn ($job): bool => $job->meeting_id === $meetingId && $job->update_data === $updateData);
    }

    /** @test */
    public function meeting_can_be_deleted(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\VerifyZoomWebhook::class]);

        Meeting::factory()->create([
            'meeting_id' => 813,
        ]);

        $postBody = File::json(
            path: base_path('tests/Fixtures/Webhooks/Zoom/meeting_delete.json'),
            flags: JSON_THROW_ON_ERROR,
        );

        $object = $postBody['payload']['object'];
        $meetingId = $object['id'];

        $this->post(route('webhooks.meetings.delete'), $postBody)
            ->assertOk()
            ->assertExactJson(['status' => 'success']);

        Queue::assertPushed(DeleteMeetingWebhook::class, fn ($job): bool => $job->meeting_id === $meetingId);
    }

    /** @test */
    public function zoom_meeting_can_be_started(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\VerifyZoomWebhook::class]);

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

        $this->post(route('webhooks.meetings.start'), $postBody)
            ->assertOk()
            ->assertExactJson(['status' => 'success']);

        Queue::assertPushed(StartMeetingWebhook::class, fn ($job): bool => (int) $job->meeting_id === (int) $meetingId && $job->start_time === $startTime);
    }

    /** @test */
    public function zoom_meeting_can_be_ended(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\VerifyZoomWebhook::class]);

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

        $this->post(route('webhooks.meetings.ended'), $postBody)
            ->assertOk()
            ->assertExactJson(['status' => 'success']);

        Queue::assertPushed(MeetingEndsWebhook::class, fn ($job): bool => (int) $job->meeting_id === (int) $meetingId && $job->start_time === $startTime && $job->end_time === $endTime);

    }

    /** @test */
    public function error_is_returned_if_the_request_was_not_sent_from_zoom(): void
    {
        $this->post(route('webhooks.meetings.update'), ['invalid_key' => 'invalid_value'])->assertForbidden();

        Queue::assertNothingPushed();
    }
}
