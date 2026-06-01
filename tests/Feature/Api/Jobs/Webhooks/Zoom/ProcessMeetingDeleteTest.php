<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Jobs\Webhooks\Zoom;

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
    public function zoom_meeting_can_be_deleted(): void
    {
        $meeting = Meeting::factory()->create([
            'meeting_id' => 813,
        ]);

        $fixture = File::json(
            path: base_path('tests/Fixtures/Webhooks/Zoom/meeting_delete.json'),
            flags: JSON_THROW_ON_ERROR,
        );

        $object = $fixture['payload']['object'];
        $meetingId = $object['id'];

        $job = new DeleteMeetingWebhook(['meeting_id' => $meetingId]);

        $job->handle();

        $this->assertModelMissing($meeting);
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

        $job = new DeleteMeetingWebhook(['meeting_id' => $meetingId]);

        $this->assertDatabaseHas('meetings', [
            'meeting_id' => 413,
        ]);

        // The job should handle a missing meeting id gracefully and not remove other meetings.
        $job->handle();

        $this->assertDatabaseHas('meetings', [
            'meeting_id' => 413,
        ]);
    }
}
