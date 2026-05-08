<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Controllers\Zoom;

use App\Exceptions\Integrations\Zoom\ZoomException;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;
use Tests\Traits\ProjectSetup;

class UpdateMeetingTest extends TestCase
{
    use InteractsWithZoom,ProjectSetup,RefreshDatabase;

    /** @test */
    public function meeting_in_database_can_be_updated(): void
    {
        $this->fakeZoom();

        $meeting = Meeting::factory()
            ->for($this->project)
            ->create(['user_id' => $this->user->id]);

        $persistedMeetingId = $meeting->meeting_id;
        $updatedDuration = 15;

        $this->withHeaders($this->idempotencyHeaders())->patchJson($this->apiV1Route('meetings.update', ['project' => $this->project, 'meeting' => $meeting]), [
            'meeting_id' => 18976,
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
    public function database_changes_are_rolled_back_if_zoom_update_fails(): void
    {
        $meeting = Meeting::factory()
            ->for($this->project)
            ->create(['user_id' => $this->user->id]);

        $updatedMeetingID = 18976;
        $updatedDuration = 15;

        $this->fakeZoom()->shouldFailWithException(
            new ZoomException('Test error message')
        );

        $this->withHeaders($this->idempotencyHeaders())->patchJson($this->apiV1Route('meetings.update', ['project' => $this->project, 'meeting' => $meeting]), [
            'meeting_id' => $updatedMeetingID,
            'duration' => $updatedDuration,
        ])->assertStatus(400);

        $this->assertDatabaseMissing('meetings', [
            'duration' => $updatedDuration,
            'meeting_id' => $updatedMeetingID,
        ]);
    }

    /** @test */
    public function it_validates_update_request(): void
    {
        $meeting = Meeting::factory()
            ->for($this->project)
            ->create(['user_id' => $this->user->id]);

        $this->withHeaders($this->idempotencyHeaders())->patchJson($this->apiV1Route('meetings.update', ['project' => $this->project, 'meeting' => $meeting]), [
            'meeting_id' => 'not-an-integer',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['meeting_id']);
    }

    /** @test */
    public function update_start_time_must_be_iso_8601_with_timezone_offset(): void
    {
        $meeting = Meeting::factory()
            ->for($this->project)
            ->create(['user_id' => $this->user->id]);

        $this->withHeaders($this->idempotencyHeaders())->patchJson($this->apiV1Route('meetings.update', ['project' => $this->project, 'meeting' => $meeting]), [
            'meeting_id' => 18976,
            'start_time' => '2024-05-18T18:00:07',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['start_time']);
    }
}
