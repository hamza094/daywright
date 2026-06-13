<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Jobs\Webhooks\Zoom;

use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Jobs\Webhooks\Zoom\UpdateMeetingWebhook;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;

class ProcessMeetingUpdateWebhookTest extends TestCase
{
    use RefreshDatabase;

    public $project;

    /**
     * A basic feature test example.
     *
     *

    /** @test */
    public function zoom_meeting_can_be_updated(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project ?? Project::factory()->create(), User::factory()->create(), [
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
        ];

        $job = new UpdateMeetingWebhook(new MeetingUpdatedWebhookData(
            meetingId: $meetingId,
            changes: $updateData,
            requestId: null,
        ));

        $job->handle();

        $this->assertSame(
            expected: $updateData['topic'],
            actual: $meeting->fresh()->topic,
        );
    }

    /** @test */
    public function zoom_meeting_update_normalizes_start_time_to_utc(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();

        $meeting = MeetingTestHelper::createMeeting($project, $user, [
            'meeting_id' => 813,
            'start_time' => Carbon::parse('2024-06-24 09:00:00', 'UTC'),
        ]);

        $job = new UpdateMeetingWebhook(new MeetingUpdatedWebhookData(
            meetingId: 813,
            changes: [
                'start_time' => '2024-06-24T13:30:00+02:00',
            ],
            requestId: null,
        ));

        $job->handle();

        $this->assertSame('2024-06-24 11:30:00', $meeting->fresh()->start_time);
    }

    /** @test */
    public function ignores_missing_meeting(): void
    {
        $job = new UpdateMeetingWebhook(new MeetingUpdatedWebhookData(
            meetingId: 999999,
            changes: ['topic' => 'Updated topic'],
            requestId: null,
        ));

        $job->handle();

        $this->assertDatabaseMissing('meetings', ['meeting_id' => 999999]);
    }

    /** @test */
    public function ignores_no_op_update(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();

        $meeting = MeetingTestHelper::createMeeting($project, $user, [
            'meeting_id' => 813,
            'topic' => 'Original topic',
            'duration' => 30,
        ]);

        $job = new UpdateMeetingWebhook(new MeetingUpdatedWebhookData(
            meetingId: 813,
            changes: [
                'topic' => 'Original topic',
                'duration' => 30,
            ],
            requestId: null,
        ));

        $job->handle();

        $this->assertSame('Original topic', $meeting->fresh()->topic);
        $this->assertSame(30, $meeting->fresh()->duration);
    }

    /** @test */
    public function ignores_unsafe_fields(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();

        $meeting = MeetingTestHelper::createMeeting($project, $user, [
            'meeting_id' => 813,
            'topic' => 'Original topic',
            'user_id' => 1,
            'project_id' => 1,
        ]);

        $job = new UpdateMeetingWebhook(new MeetingUpdatedWebhookData(
            meetingId: 813,
            changes: [
                'topic' => 'Updated topic',
                'user_id' => 999,
                'project_id' => 999,
                'id' => 999,
            ],
            requestId: null,
        ));

        $job->handle();

        $this->assertSame('Updated topic', $meeting->fresh()->topic);
        $this->assertSame(1, $meeting->fresh()->user_id);
        $this->assertSame(1, $meeting->fresh()->project_id);
    }

    /** @test */
    public function type_normalized_equivalent_values_do_not_trigger_update(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();

        $meeting = MeetingTestHelper::createMeeting($project, $user, [
            'meeting_id' => 813,
            'duration' => 30,
            'join_before_host' => true,
        ]);

        $job = new UpdateMeetingWebhook(new MeetingUpdatedWebhookData(
            meetingId: 813,
            changes: [
                'duration' => '30',
                'join_before_host' => 1,
            ],
            requestId: null,
        ));

        $job->handle();

        $this->assertSame(30, $meeting->fresh()->duration);
        $this->assertTrue((bool) $meeting->fresh()->join_before_host);
    }

    /** @test */
    public function ignores_update_if_sync_status_is_not_active(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();

        $meeting = MeetingTestHelper::createMeeting($project, $user, [
            'meeting_id' => 813,
            'topic' => 'Original topic',
            'sync_status' => MeetingSyncStatus::Failed->value,
        ]);

        $job = new UpdateMeetingWebhook(new MeetingUpdatedWebhookData(
            meetingId: 813,
            changes: ['topic' => 'Updated topic'],
            requestId: null,
        ));

        $job->handle();

        $this->assertSame('Original topic', $meeting->fresh()->topic);
    }
}
