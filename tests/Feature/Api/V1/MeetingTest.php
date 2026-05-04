<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class MeetingTest extends TestCase
{
    use LazilyRefreshDatabase, ProjectSetup;

    /** @test */
    public function it_can_show_a_meeting(): void
    {
        $meeting = Meeting::factory()->for($this->project)->for($this->user)->create();

        $this->actingAs($this->user);

        $response = $this->getJson("/api/v1/projects/{$this->project->slug}/meetings/{$meeting->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $meeting->id,
                    'meeting_id' => $meeting->meeting_id,
                ],
            ])
            ->assertJsonPath('data.start_time', Carbon::parse($meeting->start_time)->setTimezone('UTC')->toIso8601String())
            ->assertJsonPath('data.created_at', $meeting->created_at?->setTimezone('UTC')->toIso8601String());
    }

    /** @test */
    public function it_can_list_meetings_for_a_project(): void
    {
        $this->actingAs($this->user);
        // Create 5 meetings: 3 scheduled, 2 previous
        Meeting::factory()->count(3)->for($this->project)->for($this->user)->create([
            'start_time' => now()->addDays(1),
        ]);
        Meeting::factory()->count(2)->for($this->project)->for($this->user)->create([
            'start_time' => now()->subDays(1),
        ]);

        // Scheduled meetings (default)
        $response = $this->getJson("/api/v1/projects/{$this->project->slug}/meetings");
        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->assertCount(3, $response->json('data'));

        // Previous meetings
        $response = $this->getJson("/api/v1/projects/{$this->project->slug}/meetings?request=previous");
        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->assertCount(2, $response->json('data'));
    }
}
