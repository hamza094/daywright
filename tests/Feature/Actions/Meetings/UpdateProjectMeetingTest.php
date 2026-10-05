<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Meetings;

use App\Actions\Meetings\UpdateProjectMeeting;
use App\DataTransferObjects\Zoom\MeetingUpdateData;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Exceptions\Integrations\Zoom\ZoomException;
use App\Exceptions\Integrations\Zoom\ZoomMeetingOperationUnknownException;
use App\Exceptions\Integrations\Zoom\ZoomRateLimitException;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;

final class UpdateProjectMeetingTest extends TestCase
{
    use InteractsWithZoom, RefreshDatabase;

    private UpdateProjectMeeting $action;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();
        $this->zoom = $this->fakeZoom();
        $this->action = app(UpdateProjectMeeting::class);
    }

    /** @test */
    public function successful_update_saves_zoom_and_local_values(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Old Topic',
            'duration' => 30,
        ]);

        $data = new MeetingUpdateData(
            topic: 'New Topic',
            duration: 45,
        );

        $result = $this->action->handle($meeting, $this->user, $data, $this->zoom);

        $this->assertEquals('New Topic', $result->topic);
        $this->assertEquals(45, $result->duration);
        $this->assertEquals(MeetingSyncStatus::Active, $result->sync_status);
        $this->assertNull($result->sync_payload);
        $this->assertNull($result->sync_operation_id);
    }

    /** @test */
    public function zoom_failure_leaves_original_local_values_unchanged(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Old Topic',
            'duration' => 30,
        ]);

        $data = new MeetingUpdateData(
            topic: 'New Topic',
            duration: 45,
        );

        $this->zoom->shouldFailWithException(new ZoomException('Invalid request', 400));

        try {
            $this->action->handle($meeting, $this->user, $data, $this->zoom);
            $this->fail('Expected ZoomException to be thrown.');
        } catch (ZoomException) {
            $meeting->refresh();

            $this->assertEquals('Old Topic', $meeting->topic);
            $this->assertEquals(30, $meeting->duration);
            $this->assertEquals(MeetingSyncStatus::UpdateFailed, $meeting->sync_status);
        }
    }

    /** @test */
    public function timeout_leaves_operation_recoverable(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Old Topic',
        ]);

        $data = new MeetingUpdateData(
            topic: 'New Topic',
        );

        $this->zoom->shouldFailWithException(new ZoomMeetingOperationUnknownException('Connection timeout'));

        try {
            $this->action->handle($meeting, $this->user, $data, $this->zoom);
            $this->fail('Expected ZoomMeetingOperationUnknownException to be thrown.');
        } catch (ZoomMeetingOperationUnknownException) {
            $meeting->refresh();

            $this->assertEquals('Old Topic', $meeting->topic);
            $this->assertEquals(MeetingSyncStatus::Updating, $meeting->sync_status);
            $this->assertNotNull($meeting->sync_available_at);
        }
    }

    /** @test */
    public function operation_id_prevents_stale_update(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Original Topic',
        ]);

        $data = new MeetingUpdateData(
            topic: 'New Topic',
        );

        $result = $this->action->handle($meeting, $this->user, $data, $this->zoom);

        $originalOperationId = $result->sync_operation_id;

        $meeting->update([
            'sync_operation_id' => Str::uuid()->toString(),
            'sync_status' => MeetingSyncStatus::Updating,
        ]);

        $this->assertEquals('New Topic', $result->topic);
        $this->assertNotEquals($originalOperationId, $meeting->sync_operation_id);
    }

    /** @test */
    public function second_update_is_rejected_while_one_is_active(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Original Topic',
            'sync_status' => MeetingSyncStatus::Updating,
        ]);

        $data = new MeetingUpdateData(
            topic: 'New Topic',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Meeting must be active or update_failed to update');

        $this->action->handle($meeting, $this->user, $data, $this->zoom);
    }

    /** @test */
    public function rate_limit_rolls_back_to_active_for_manual_retry(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Old Topic',
        ]);

        $data = new MeetingUpdateData(
            topic: 'New Topic',
        );

        $this->zoom->shouldFailWithException(new ZoomRateLimitException(60, 'Rate limited'));

        try {
            $this->action->handle($meeting, $this->user, $data, $this->zoom);
            $this->fail('Expected ZoomRateLimitException to be thrown.');
        } catch (ZoomRateLimitException) {
            $meeting->refresh();

            $this->assertEquals(MeetingSyncStatus::Active, $meeting->sync_status);
            $this->assertEquals('Old Topic', $meeting->topic);
            $this->assertNull($meeting->sync_payload);
            $this->assertNull($meeting->sync_operation_id);
        }
    }

    /** @test */
    public function sync_payload_is_encrypted(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Old Topic',
        ]);

        $data = new MeetingUpdateData(
            topic: 'New Topic',
        );

        $this->action->handle($meeting, $this->user, $data, $this->zoom);

        $meeting->refresh();

        $this->assertNull($meeting->sync_payload);
    }

    /** @test */
    public function password_payload_is_never_written_to_logs(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Old Topic',
        ]);

        $data = new MeetingUpdateData(
            topic: 'New Topic',
            password: 'secret123',
        );

        $this->action->handle($meeting, $this->user, $data, $this->zoom);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'topic' => 'New Topic',
        ]);
    }
}
