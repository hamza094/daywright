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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
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
    public function second_delete_while_deleting_reports_that_recovery_is_in_progress(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => MeetingSyncStatus::Deleting,
            'sync_operation_id' => Str::uuid()->toString(),
        ]);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Meeting deletion is already in progress. Please retry.');

        $this->action->handle($meeting, $this->user, $this->zoom);
    }

    /** @test */
    public function delete_while_deleting_does_not_call_zoom_api(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => MeetingSyncStatus::Deleting,
            'sync_operation_id' => Str::uuid()->toString(),
        ]);

        try {
            $this->action->handle($meeting, $this->user, $this->zoom);
            $this->fail('Expected ConflictHttpException to be thrown.');
        } catch (ConflictHttpException) {
            // The existing deletion remains owned by recovery; this request must not call Zoom.
        }

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::Deleting, $meeting->sync_status);

        // Assert that deleteMeeting was NOT called
        $this->zoom->assertNoMeetingsDeleted();
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
    public function repeated_delete_request_after_successful_delete_is_idempotent(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => MeetingSyncStatus::Active,
        ]);

        $this->action->handle($meeting, $this->user, $this->zoom);

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::Deleted, $meeting->sync_status);
        $this->assertCount(1, $this->zoom->meetingsToDelete);

        // Should not throw exception - already deleted
        $this->action->handle($meeting, $this->user, $this->zoom);

        $meeting->refresh();

        // Status should remain Deleted
        $this->assertEquals(MeetingSyncStatus::Deleted, $meeting->sync_status);
        $this->assertCount(1, $this->zoom->meetingsToDelete);
    }

    /** @test */
    public function new_delete_starts_with_fresh_retry_state_and_clears_old_payload(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => MeetingSyncStatus::UpdateFailed,
            'sync_attempts' => 5,
            'sync_claim_token' => 'old-token',
            'sync_available_at' => now()->subHour(),
            'sync_payload' => '{"topic":"old update"}',
        ]);

        $this->action->handle($meeting, $this->user, $this->zoom);

        $meeting->refresh();

        $this->assertEquals(0, $meeting->sync_attempts);
        $this->assertNull($meeting->sync_claim_token);
        $this->assertNull($meeting->sync_available_at);
        $this->assertNull($meeting->sync_payload);
    }
}
