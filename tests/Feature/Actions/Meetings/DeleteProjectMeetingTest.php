<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Meetings;

use App\Actions\Meetings\DeleteProjectMeeting;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Exceptions\Integrations\Zoom\NotFoundException;
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

final class DeleteProjectMeetingTest extends TestCase
{
    use InteractsWithZoom, RefreshDatabase;

    private DeleteProjectMeeting $action;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();
        $this->zoom = $this->fakeZoom();
        $this->action = app(DeleteProjectMeeting::class);
    }

    /** @test */
    public function successful_delete_changes_status_to_deleted(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => MeetingSyncStatus::Active,
        ]);

        $this->action->handle($meeting, $this->user, $this->zoom);

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::Deleted, $meeting->sync_status);
        $this->assertNull($meeting->sync_operation_id);
        $this->assertNull($meeting->sync_operation_type);
    }

    /** @test */
    public function zoom_404_is_treated_as_success(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => MeetingSyncStatus::Active,
        ]);

        $this->zoom->shouldFailWithException(new NotFoundException);

        $this->action->handle($meeting, $this->user, $this->zoom);

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::Deleted, $meeting->sync_status);
    }

    /** @test */
    public function temporary_zoom_failure_changes_status_to_delete_failed(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => MeetingSyncStatus::Active,
        ]);

        $this->zoom->shouldFailWithException(new ZoomException('Temporary failure', 500));

        try {
            $this->action->handle($meeting, $this->user, $this->zoom);
            $this->fail('Expected ZoomException to be thrown.');
        } catch (ZoomException) {
            $meeting->refresh();

            $this->assertEquals(MeetingSyncStatus::DeleteFailed, $meeting->sync_status);
            $this->assertNotNull($meeting->sync_error);
        }
    }

    /** @test */
    public function timeout_leaves_operation_recoverable(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => MeetingSyncStatus::Active,
        ]);

        $this->zoom->shouldFailWithException(new ZoomMeetingOperationUnknownException('Connection timeout'));

        try {
            $this->action->handle($meeting, $this->user, $this->zoom);
            $this->fail('Expected ZoomMeetingOperationUnknownException to be thrown.');
        } catch (ZoomMeetingOperationUnknownException) {
            $meeting->refresh();

            $this->assertEquals(MeetingSyncStatus::Deleting, $meeting->sync_status);
            $this->assertNotNull($meeting->sync_available_at);
        }
    }

    /** @test */
    public function rate_limit_rolls_back_to_active_for_manual_retry(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => MeetingSyncStatus::Active,
        ]);

        $this->zoom->shouldFailWithException(new ZoomRateLimitException(60, 'Rate limited'));

        try {
            $this->action->handle($meeting, $this->user, $this->zoom);
            $this->fail('Expected ZoomRateLimitException to be thrown.');
        } catch (ZoomRateLimitException) {
            $meeting->refresh();

            $this->assertEquals(MeetingSyncStatus::Active, $meeting->sync_status);
            $this->assertNull($meeting->sync_operation_id);
            $this->assertNull($meeting->sync_operation_type);
        }
    }

    /** @test */
    public function operation_id_prevents_stale_delete(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => MeetingSyncStatus::Active,
        ]);

        $this->action->handle($meeting, $this->user, $this->zoom);

        $originalOperationId = $meeting->sync_operation_id;

        $meeting->update([
            'sync_operation_id' => Str::uuid()->toString(),
            'sync_status' => MeetingSyncStatus::Deleting,
        ]);

        $this->assertNotEquals($originalOperationId, $meeting->sync_operation_id);
    }

    /** @test */
    public function second_delete_is_rejected_while_one_is_active(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => MeetingSyncStatus::Deleting,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Meeting must be active, update_failed, or delete_failed to delete');

        $this->action->handle($meeting, $this->user, $this->zoom);
    }

    /** @test */
    public function local_row_is_kept_as_tombstone(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => MeetingSyncStatus::Active,
        ]);

        $this->action->handle($meeting, $this->user, $this->zoom);

        $meeting->refresh();

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'sync_status' => MeetingSyncStatus::Deleted->value,
        ]);
    }

    /** @test */
    public function repeated_delete_request_after_successful_delete_is_safe(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => MeetingSyncStatus::Active,
        ]);

        $this->action->handle($meeting, $this->user, $this->zoom);

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::Deleted, $meeting->sync_status);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Meeting must be active, update_failed, or delete_failed to delete');

        $this->action->handle($meeting, $this->user, $this->zoom);
    }
}
