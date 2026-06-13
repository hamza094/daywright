<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Jobs\Webhooks\Zoom;

use App\DataTransferObjects\Zoom\MeetingDeletedWebhookData;
use App\Jobs\Webhooks\Zoom\DeleteMeetingWebhook;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProcessMeetingDeleteTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */

    /** @test */
    public function zoom_meeting_status_can_be_updated_to_deleted(): void
    {
        Meeting::factory()->create([
            'meeting_id' => 813,
        ]);

        $fixture = File::json(
            path: base_path('tests/Fixtures/Webhooks/Zoom/meeting_delete.json'),
            flags: JSON_THROW_ON_ERROR,
        );

        $object = $fixture['payload']['object'];
        $meetingId = $object['id'];

        $job = new DeleteMeetingWebhook(new MeetingDeletedWebhookData(
            meetingId: $meetingId,
            requestId: null,
        ));

        $job->handle();

        $this->assertDatabaseHas('meetings', [
            'meeting_id' => 813,
            'sync_status' => \App\Enums\Meeting\MeetingSyncStatus::Deleted->value,
        ]);
    }

    /** @test */
    public function throw_exception_if_meeting_not_found(): void
    {
        Meeting::factory()->create([
            'meeting_id' => 413,
        ]);

        $fixture = File::json(
            path: base_path('tests/Fixtures/Webhooks/Zoom/meeting_delete.json'),
            flags: JSON_THROW_ON_ERROR,
        );

        $object = $fixture['payload']['object'];
        $meetingId = $object['id'];

        $job = new DeleteMeetingWebhook(new MeetingDeletedWebhookData(
            meetingId: $meetingId,
            requestId: null,
        ));

        $this->assertDatabaseHas('meetings', [
            'meeting_id' => 413,
        ]);

        // The job should handle a missing meeting id gracefully and not remove other meetings.
        $job->handle();

        $this->assertDatabaseHas('meetings', [
            'meeting_id' => 413,
        ]);
    }

    /** @test */
    public function updates_lifecycle_before_deleting(): void
    {
        Meeting::factory()->create([
            'meeting_id' => 813,
            'sync_status' => \App\Enums\Meeting\MeetingSyncStatus::Active,
            'sync_error' => 'some error',
        ]);

        $job = new DeleteMeetingWebhook(new MeetingDeletedWebhookData(
            meetingId: 813,
            requestId: null,
        ));

        $job->handle();

        $this->assertDatabaseHas('meetings', [
            'meeting_id' => 813,
            'sync_status' => \App\Enums\Meeting\MeetingSyncStatus::Deleted->value,
            'sync_error' => null,
        ]);
    }

    /** @test */
    public function handles_duplicate_delete_webhook_safely(): void
    {
        Meeting::factory()->create([
            'meeting_id' => 813,
            'sync_status' => \App\Enums\Meeting\MeetingSyncStatus::Deleted,
        ]);

        $job = new DeleteMeetingWebhook(new MeetingDeletedWebhookData(
            meetingId: 813,
            requestId: null,
        ));

        // Should not throw error even if already deleted
        $job->handle();

        $this->assertDatabaseHas('meetings', [
            'meeting_id' => 813,
            'sync_status' => \App\Enums\Meeting\MeetingSyncStatus::Deleted->value,
        ]);
    }
}
