<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Meetings;

use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;
use Tests\Traits\ProjectSetup;

/**
 * Feature tests for meeting update API endpoint.
 *
 * Tests the PATCH /api/v1/meetings/{meeting} endpoint which updates meetings in both
 * the local database and Zoom via API integration. These tests verify:
 * - Successful meeting updates with proper Zoom API interaction
 * - Error handling when Zoom API fails
 * - Request validation
 * - Sync status transitions (active → updating → active/failed)
 * - Idempotency guarantees
 * - Database integrity and rollback behavior
 *
 * Level: Feature/Integration testing
 */
class MeetingUpdateTest extends TestCase
{
    use InteractsWithZoom,ProjectSetup,RefreshDatabase;

    /** @test */
    public function meeting_can_be_updated_successfully(): void
    {
        $this->fakeZoom();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user);

        $persistedMeetingId = $meeting->meeting_id;
        $updatedDuration = 15;

        $this->withHeaders($this->idempotencyHeaders())->patchJson($this->apiV1Route('meetings.update', ['project' => $this->project, 'meeting' => $meeting]), [
            'duration' => $updatedDuration,
        ])->assertStatus(200)
            ->assertJsonPath('data.meeting_id', $persistedMeetingId)
            ->assertJsonPath('data.duration', $updatedDuration);

        $this->assertDatabaseHas('meetings', [
            'duration' => $updatedDuration,
            'meeting_id' => $persistedMeetingId,
        ]);

    }

    /** @test */
    public function it_validates_update_request(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user);

        $this->withHeaders($this->idempotencyHeaders())->patchJson($this->apiV1Route('meetings.update', ['project' => $this->project, 'meeting' => $meeting]), [
            'meeting_id' => 18976,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['meeting_id']);
    }

    /** @test */
    public function update_start_time_must_be_iso_8601_with_timezone_offset(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user);

        $this->withHeaders($this->idempotencyHeaders())->patchJson($this->apiV1Route('meetings.update', ['project' => $this->project, 'meeting' => $meeting]), [
            'meeting_id' => 18976,
            'start_time' => '2024-05-18T18:00:07',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['meeting_id', 'start_time']);
    }

    /** @test */
    public function it_marks_updating_and_clears_old_sync_error(): void
    {
        $this->fakeZoom();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => 'update_failed',
            'sync_error' => 'Previous error',
        ]);

        $this->withHeaders($this->idempotencyHeaders())->patchJson($this->apiV1Route('meetings.update', ['project' => $this->project, 'meeting' => $meeting]), [
            'duration' => 15,
        ])->assertStatus(200);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'sync_status' => 'active',
            'sync_error' => null,
        ]);
    }

    /** @test */
    public function it_marks_update_failed_on_zoom_failure(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user);

        $this->fakeZoom()->shouldFailWithException(
            new ZoomUserErrorException('Test error message')
        );

        $this->withHeaders($this->idempotencyHeaders())->patchJson($this->apiV1Route('meetings.update', ['project' => $this->project, 'meeting' => $meeting]), [
            'duration' => 15,
        ])->assertStatus(400);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'sync_status' => 'update_failed',
        ]);
        $this->assertNotNull($meeting->fresh()->sync_error);
    }

    /** @test */
    public function it_does_not_apply_local_changes_on_zoom_failure(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'duration' => 30,
        ]);

        $originalDuration = $meeting->duration;

        $this->fakeZoom()->shouldFailWithException(
            new ZoomUserErrorException('Test error message')
        );

        $this->withHeaders($this->idempotencyHeaders())->patchJson($this->apiV1Route('meetings.update', ['project' => $this->project, 'meeting' => $meeting]), [
            'duration' => 15,
        ])->assertStatus(400);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'duration' => $originalDuration,
        ]);
    }

    /** @test */
    public function it_clears_sync_error_and_sets_synced_at_on_success(): void
    {
        $this->fakeZoom();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => 'update_failed',
            'sync_error' => 'Previous error',
            'synced_at' => null,
        ]);

        $this->withHeaders($this->idempotencyHeaders())->patchJson($this->apiV1Route('meetings.update', ['project' => $this->project, 'meeting' => $meeting]), [
            'duration' => 15,
        ])->assertStatus(200);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'sync_status' => 'active',
            'sync_error' => null,
        ]);
        $this->assertNotNull($meeting->fresh()->synced_at);
    }

    /** @test */
    public function same_idempotency_key_is_safe_for_update(): void
    {
        $this->fakeZoom();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user);

        $headers = $this->idempotencyHeaders();

        // First update
        $this->patchJson($this->apiV1Route('meetings.update', ['project' => $this->project, 'meeting' => $meeting]), [
            'duration' => 15,
        ], $headers)->assertStatus(200);

        // Second update with same idempotency key
        $this->patchJson($this->apiV1Route('meetings.update', ['project' => $this->project, 'meeting' => $meeting]), [
            'duration' => 15,
        ], $headers)->assertStatus(200);

        // Should not cause errors - idempotency should be safe
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'duration' => 15, // First value should persist
        ]);
    }
}
