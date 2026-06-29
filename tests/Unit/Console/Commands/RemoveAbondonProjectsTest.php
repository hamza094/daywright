<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Jobs\CancelZoomMeetingsJob;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RemoveAbondonProjectsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_deletes_abandoned_projects_past_limit(): void
    {
        $user = User::factory()->create();

        // Project within limit (should not be deleted)
        Project::factory()
            ->for($user)
            ->create(['deleted_at' => now()->subDays(30)]);

        // Project past limit (should be deleted)
        $abandonedProject = Project::factory()
            ->for($user)
            ->create(['deleted_at' => now()->subDays(91)]);

        $this->artisan('remove:abandon')->assertSuccessful();

        $this->assertDatabaseHas('projects', [
            'id' => Project::onlyTrashed()->first()->id,
        ]);

        $this->assertDatabaseMissing('projects', [
            'id' => $abandonedProject->id,
        ]);
    }

    /** @test */
    public function it_dispatches_zoom_cancellation_when_meetings_exist(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $project = Project::factory()
            ->for($user)
            ->create(['deleted_at' => now()->subDays(91)]);

        // Create Zoom meetings for the project
        Meeting::factory()
            ->for($project)
            ->for($user)
            ->count(3)
            ->create();

        $this->artisan('remove:abandon')->assertSuccessful();

        Queue::assertPushed(CancelZoomMeetingsJob::class);
    }

    /** @test */
    public function it_processes_projects_in_chunks(): void
    {
        $user = User::factory()->create();

        // Create 250 abandoned projects (more than chunk size of 100)
        Project::factory()
            ->for($user)
            ->count(250)
            ->create(['deleted_at' => now()->subDays(91)]);

        $this->artisan('remove:abandon')->assertSuccessful();

        // All should be deleted
        $this->assertEquals(0, Project::onlyTrashed()->count());
    }

    /** @test */
    public function non_trashed_projects_are_ignored(): void
    {
        $user = User::factory()->create();

        // Active project (should not be deleted)
        $activeProject = Project::factory()
            ->for($user)
            ->create(['deleted_at' => null]);

        // Trashed project within limit (should not be deleted)
        $recentTrashed = Project::factory()
            ->for($user)
            ->create(['deleted_at' => now()->subDays(30)]);

        $this->artisan('remove:abandon')->assertSuccessful();

        // Active project should still exist
        $this->assertDatabaseHas('projects', [
            'id' => $activeProject->id,
            'deleted_at' => null,
        ]);

        // Recently trashed project should still exist
        $this->assertDatabaseHas('projects', [
            'id' => $recentTrashed->id,
        ]);
    }
}
