<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Tasks;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class TaskLifecycleTest extends TestCase
{
    use ProjectSetup;
    use RefreshDatabase;

    /** @test */
    public function allowed_user_can_archive_and_unarchive_task(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->patchJson(route('api.v1.task.archive', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]))
            ->assertOk()
            ->assertJsonPath('message', 'Project task archived successfully');

        $this->assertSoftDeleted($task);

        $this->patchJson(route('api.v1.task.unarchive', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]))
            ->assertOk()
            ->assertJsonPath('message', 'Project task restored successfully');

        $this->assertNotSoftDeleted($task);
    }

    /** @test */
    public function allowed_user_can_remove_archived_task_from_database(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->patchJson(route('api.v1.task.archive', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]));

        $this->assertSoftDeleted($task);

        $this->deleteJson($this->apiV1ProjectTaskRoute('tasks.destroy', $this->project, $task))
            ->assertOk()
            ->assertJson([
                'message' => 'Task deleted successfully.',
            ]);

        $this->assertModelMissing($task);
    }

    /** @test */
    public function active_task_cannot_be_removed_until_it_is_archived(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->deleteJson($this->apiV1ProjectTaskRoute('tasks.destroy', $this->project, $task))
            ->assertForbidden()
            ->assertJson([
                'message' => 'Task must be trashed to perform this action',
            ]);

        $this->assertModelExists($task);
    }

    /** @test */
    public function it_enforces_task_limit_on_restore(): void
    {
        config(['plan-limits.free.max_tasks_per_project' => 2]);

        // Ensure user is on Free plan
        $this->user->subscriptions()->delete();
        $this->user->customer()->delete();

        $task1 = Task::factory()->for($this->project)->create();
        $task2 = Task::factory()->for($this->project)->create();
        $task3 = Task::factory()->for($this->project)->create();

        $task3->delete();

        $this->assertSoftDeleted($task3);

        $response = $this->patchJson(route('api.v1.task.unarchive', [
            'project' => $this->project->slug,
            'task' => $task3->id,
        ]));

        $response->assertStatus(403)
            ->assertJsonPath('code', 'plan_limit_exceeded');
    }
}
