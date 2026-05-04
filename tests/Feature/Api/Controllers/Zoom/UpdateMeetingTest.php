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

        $updatedMeetingID = 18976;
        $updatedDuration = 15;

        $this->patchJson('/api/v1/projects/'.$this->project->slug.'/meetings/'.$meeting->id, [
            'meeting_id' => $updatedMeetingID,
            'duration' => $updatedDuration,
        ])->assertStatus(200)
            ->assertJsonPath('data.meeting_id', $updatedMeetingID)
            ->assertJsonPath('data.duration', $updatedDuration);

        $this->assertDatabaseHas('meetings', [
            'duration' => $updatedDuration,
            'meeting_id' => $updatedMeetingID,
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

        $this->patchJson('/api/v1/projects/'.$this->project->slug.'/meetings/'.$meeting->id, [
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

        $this->patchJson('/api/v1/projects/'.$this->project->slug.'/meetings/'.$meeting->id, [
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

        $this->patchJson('/api/v1/projects/'.$this->project->slug.'/meetings/'.$meeting->id, [
            'meeting_id' => 18976,
            'start_time' => '2024-05-18T18:00:07',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['start_time']);
    }
}
