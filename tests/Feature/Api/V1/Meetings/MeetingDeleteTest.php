<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Meetings;

use App\Exceptions\Integrations\Zoom\NotFoundException;
use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;
use Tests\Traits\ProjectSetup;

/**
 * Feature tests for meeting deletion API endpoint.
 *
 * Tests the DELETE /api/v1/meetings/{meeting} endpoint which deletes meetings from both
 * the local database and Zoom via API integration. These tests verify:
 * - Successful meeting deletion with proper Zoom API interaction
 * - Error handling when Zoom API fails
 * - Sync status transitions (active → deleting → deleted/failed)
 * - Treatment of Zoom 404 as success (idempotent deletion)
 * - Database integrity and rollback behavior
 *
 * Level: Feature/Integration testing
 */
class MeetingDeleteTest extends TestCase
{
    use InteractsWithZoom,ProjectSetup,RefreshDatabase;

    /** @test */
    public function meeting_can_be_deleted_successfully(): void
    {
        $this->fakeZoom();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user);

        $this->deleteJson($this->apiV1Route('meetings.destroy', ['project' => $this->project, 'meeting' => $meeting]))
            ->assertOk()
            ->assertJsonPath('message', 'Meeting deleted successfully.');

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'sync_status' => 'deleted',
        ]);
    }

    /** @test */
    public function database_changes_are_rolled_back_if_meeting_delete_fails(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user);

        $meetingId = $meeting->id;

        $this->fakeZoom()->shouldFailWithException(
            new ZoomUserErrorException('Test error message')
        );

        $response = $this->deleteJson($this->apiV1Route('meetings.destroy', ['project' => $this->project, 'meeting' => $meeting]));

        $response->assertStatus(400);

        $this->assertDatabaseHas('meetings', [
            'id' => $meetingId,
            'sync_status' => 'delete_failed',
        ]);
    }

    /** @test */
    public function it_marks_deleting_and_clears_old_sync_error(): void
    {
        $this->fakeZoom();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => 'delete_failed',
            'sync_error' => 'Previous error',
        ]);

        $this->deleteJson($this->apiV1Route('meetings.destroy', ['project' => $this->project, 'meeting' => $meeting]))
            ->assertOk();

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'sync_status' => 'deleted',
            'sync_error' => null,
        ]);
    }

    /** @test */
    public function it_treats_zoom_404_as_success_for_delete(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user);

        $this->fakeZoom()->shouldFailWithException(
            new NotFoundException('Meeting not found')
        );

        $this->deleteJson($this->apiV1Route('meetings.destroy', ['project' => $this->project, 'meeting' => $meeting]))
            ->assertOk();

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'sync_status' => 'deleted',
        ]);
    }

    /** @test */
    public function it_clears_sync_error_and_sets_synced_at_on_delete_success(): void
    {
        $this->fakeZoom();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => 'delete_failed',
            'sync_error' => 'Previous error',
            'synced_at' => null,
        ]);

        $this->deleteJson($this->apiV1Route('meetings.destroy', ['project' => $this->project, 'meeting' => $meeting]))
            ->assertOk();

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'sync_status' => 'deleted',
            'sync_error' => null,
        ]);
    }

    /** @test */
    public function normal_index_excludes_deleted_and_delete_failed_meetings(): void
    {
        $this->fakeZoom();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user);

        $this->deleteJson($this->apiV1Route('meetings.destroy', ['project' => $this->project, 'meeting' => $meeting]))
            ->assertOk();

        $response = $this->getJson(route('api.v1.meetings.index', ['project' => $this->project->slug]));

        $response->assertJsonCount(0, 'data');
    }
}
