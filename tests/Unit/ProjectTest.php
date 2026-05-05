<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Project;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic unit test example.
     */

    /** @test */
    public function a_project_can_make_a_string_path(): void
    {
        $project = Project::factory()->create();
        $this->assertEquals(
            $this->apiV1Route('projects.show', ['project' => $project]), $project->path());
    }

    /** @test */
    public function a_project_has_a_creator(): void
    {
        $project = Project::factory()->create();
        $this->assertInstanceOf(User::class, $project->user);
    }

    /** @test */
    public function project_belongs_to_stage(): void
    {
        $project = Project::factory()->create();
        $this->assertInstanceOf(\App\Models\Stage::class, $project->stage);
    }

    /** @test */
    public function a_project_can_add_a_task(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        TaskStatus::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $project->addTask('run berry run');
        $this->assertCount(1, $project->tasks);
    }

    /** @test */
    public function a_project_has_tasks(): void
    {
        $project = Project::factory()->create();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $project->tasks);
    }

    /** @test */
    public function invitation_can_be_sent_to_a_user(): void
    {
        $project = Project::factory()->create();
        $project->invite($user = User::factory()->create());
        $this->assertTrue($project->members->contains($user));
    }

    protected function addMember($project, $user)
    {
        $project
            ->members()
            ->attach($user, ['active' => true]);
    }
}
