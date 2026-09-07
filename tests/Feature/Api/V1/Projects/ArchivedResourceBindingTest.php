<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Projects;

use App\Exceptions\ArchivedResourceException;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Runtime binding tests for archived resource handling.
 *
 * Tests that the custom route binding in Project model correctly throws
 * ArchivedResourceException when accessing trashed resources.
 */
class ArchivedResourceBindingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function active_project_is_accessible(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $project->members()->attach($this->user->id);

        $response = $this->getJson(route('api.v1.projects.show', $project->slug));

        $response->assertOk();
    }

    /** @test */
    public function missing_project_returns_404(): void
    {
        $response = $this->getJson(route('api.v1.projects.show', 'non-existent-slug'));

        $response->assertNotFound();
    }

    /** @test */
    public function archived_project_is_accessible_with_withtrashed(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $project->members()->attach($this->user->id);
        $project->delete(); // Soft delete

        // Routes with withTrashed() can access archived projects
        $response = $this->getJson(route('api.v1.projects.show', $project->slug));

        $response->assertOk();
    }

    /** @test */
    public function archived_project_is_rejected_by_a_route_without_withtrashed(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $project->members()->attach($this->user->id);
        $project->delete();

        $this->getJson(route('api.v1.projects.activities', $project->slug))
            ->assertConflict()
            ->assertJsonPath('code', 'project_archived');
    }

    /** @test */
    public function active_task_is_accessible(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $project->members()->attach($this->user->id);
        $task = Task::factory()->create(['project_id' => $project->id]);

        $response = $this->getJson(route('api.v1.tasks.show', [
            'project' => $project->slug,
            'task' => $task->id,
        ]));

        $response->assertOk();
    }

    /** @test */
    public function missing_task_returns_404(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $project->members()->attach($this->user->id);

        $response = $this->getJson(route('api.v1.tasks.show', [
            'project' => $project->slug,
            'task' => 99999,
        ]));

        $response->assertNotFound();
    }

    /** @test */
    public function archived_task_is_accessible_with_withtrashed(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $project->members()->attach($this->user->id);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $task->delete(); // Soft delete

        // Routes with withTrashed() can access archived tasks
        $response = $this->getJson(route('api.v1.tasks.show', [
            'project' => $project->slug,
            'task' => $task->id,
        ]));

        $response->assertOk();
    }

    /** @test */
    public function archived_project_limits_is_accessible_with_withtrashed(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $project->members()->attach($this->user->id);
        $project->delete();

        // Routes with withTrashed() can access archived projects
        $response = $this->getJson(route('api.v1.projects.limits', $project->slug));

        $response->assertOk();
    }

    /** @test */
    public function archived_project_force_delete_is_accessible(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $project->members()->attach($this->user->id);
        $project->delete();

        // Force delete should work on archived projects
        $response = $this->deleteJson(route('api.v1.projects.force-delete', $project->slug));

        $response->assertOk();
    }

    /** @test */
    public function archived_project_restore_is_accessible(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $project->members()->attach($this->user->id);
        $project->delete();

        // Restore should work on archived projects
        $response = $this->patchJson(route('api.v1.projects.restore', $project->slug));

        $response->assertOk();
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'deleted_at' => null,
        ]);
    }

    /** @test */
    public function archived_task_restore_is_accessible(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $project->members()->attach($this->user->id);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $task->delete();

        // Restore should work on archived tasks
        $response = $this->patchJson(route('api.v1.task.unarchive', [
            'project' => $project->slug,
            'task' => $task->id,
        ]));

        $response->assertOk();
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'deleted_at' => null,
        ]);
    }
}
