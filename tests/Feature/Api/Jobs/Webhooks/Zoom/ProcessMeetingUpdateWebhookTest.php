<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Jobs\Webhooks\Zoom;

use App\Jobs\Webhooks\Zoom\UpdateMeetingWebhook;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProcessMeetingUpdateWebhookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     *
     *

    /** @test */
    public function zoom_meeting_can_be_updated(): void
    {
        $meeting = Meeting::factory()->create([
            'meeting_id' => 813,
            'topic' => 'shining in the sky',
        ]);

        $fixture = File::json(
            path: base_path('tests/Fixtures/Webhooks/Zoom/meeting_update.json'),
            flags: JSON_THROW_ON_ERROR,
        );

        $object = $fixture['payload']['object'];
        $meetingId = $object['id'];
        $updateData = [
            'topic' => $object['topic'],
            'host_id' => 'provider-host-id',
            'settings' => ['waiting_room' => true],
        ];

        $job = new UpdateMeetingWebhook([
            'meeting_id' => $meetingId,
            'update_data' => $updateData,
        ]);

        $job->handle();

        $this->assertSame(
            expected: $updateData['topic'],
            actual: $meeting->fresh()->topic,
        );
    }

    /** @test */
    public function zoom_meeting_update_normalizes_start_time_to_utc(): void
    {
        $meeting = Meeting::factory()->create([
            'meeting_id' => 813,
            'start_time' => Carbon::parse('2024-06-24 09:00:00', 'UTC'),
        ]);

        $job = new UpdateMeetingWebhook([
            'meeting_id' => 813,
            'update_data' => [
                'start_time' => '2024-06-24T13:30:00+02:00',
            ],
        ]);

        $job->handle();

        $this->assertSame('2024-06-24 11:30:00', $meeting->fresh()->start_time);
    }
}
