<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Controllers\Zoom;

use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;
use Tests\Traits\ProjectSetup;

class DeleteMeetingTest extends TestCase
{
    use InteractsWithZoom,ProjectSetup,RefreshDatabase;

    /** @test */
    public function meeting_can_be_deleted(): void
    {
        $this->fakeZoom();

        $meeting = Meeting::factory()
            ->for($this->project)
            ->create(['user_id' => $this->user->id]);

        $this->deleteJson($this->apiV1Route('meetings.destroy', ['project' => $this->project, 'meeting' => $meeting]))
            ->assertOk()
            ->assertJsonPath('message', 'Meeting deleted successfully.');

        $this->assertModelMissing($meeting);
    }

    /** @test */
    public function database_changes_are_rolled_back_if_meeting_delete_fails(): void
    {
        $meeting = Meeting::factory()
            ->for($this->project)
            ->create(['user_id' => $this->user->id]);

        $meetingId = $meeting->id;

        $this->fakeZoom()->shouldFailWithException(
            new ZoomUserErrorException('Test error message')
        );

        $response = $this->deleteJson($this->apiV1Route('meetings.destroy', ['project' => $this->project, 'meeting' => $meeting]));

        $response->assertStatus(400);

        $this->assertDatabaseHas('meetings', [
            'id' => $meetingId,
        ]);
    }
}
