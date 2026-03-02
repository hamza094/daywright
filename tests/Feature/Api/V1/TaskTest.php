<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Traits\ProjectSetup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    /** @test */
    public function allowed_user_see_archived_tasks(): void
    {
        Task::factory(['deleted_at' => now()])
            ->count(3)
            ->for($this->project)
            ->create();

        $this->getJson($this->project->path().'/tasks?request=archived')
            ->assertOk()
            ->assertJsonCount(3, 'tasksData')
            ->assertJsonStructure(['message', 'tasksData']);
    }

    /** @test */
    public function allowed_user_see_active_tasks_and_paginate(): void
    {
        Task::factory()
            ->count(9)
            ->for($this->project)
            ->create();

        $this->getJson($this->project->path().'/tasks')
            ->assertOk()
            ->assertJsonCount(3, 'tasksData')
            ->assertJsonStructure([
                'message',
                'tasksData' => [
                    'data' => [], 'links', 'meta',
                ],
            ]);
    }

    /** @test */
    public function task_requires_a_title(): void
    {
        $task = Task::factory()->make(['title' => null, 'project_id' => $this->project->id]);

        $this->postJson($task->path(), $task->toArray())->assertJsonValidationErrors('title');
    }

    /** @test */
    public function allowed_user_can_create_projects_task(): void
    {
        $this->postJson($this->project->path().'/tasks', [
            'title' => 'My Project Task',
            'status_id' => $this->status->id,
        ])->assertCreated()
            ->assertJson([
                'task' => [
                    'id' => 1,
                    'title' => 'My Project Task',
                ]]);

        $this->assertDatabaseHas('tasks', ['title' => 'My Project Task']);
    }

    /** @test */
    public function user_cannot_create_task_when_project_is_abandoned(): void
    {
        $this->project->delete();

        $this->postJson(route('tasks.store', ['project' => $this->project->slug]), [
            'title' => 'Blocked Task',
            'status_id' => $this->status->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('tasks', [
            'project_id' => $this->project->id,
            'title' => 'Blocked Task',
        ]);
    }

    /** @test */
    public function duplicate_project_task_can_not_be_created(): void
    {
        $this->project->tasks()->create([
            'title' => 'Project Task',
            'user_id' => $this->project->user->id,
        ]);

        $this->postJson($this->project->path().'/tasks', [
            'title' => 'Project Task',
            'status_id' => $this->status->id,
        ])->assertJsonValidationErrors('title');

        $project2 = Project::factory()->for($this->user)->create();

        $this->postJson($project2->path().'/tasks',
            ['title' => 'Project Task'])
            ->assertCreated();
    }

    /** @test */
    public function task_limit_per_project(): void
    {
        Task::factory()->count((int) config('app.project.taskLimit'))
            ->for($this->project)->create();

        $this->postJson($this->project->path().'/tasks',
            ['title' => 'Project Task'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tasks');
    }

    /** @test */
    public function allowed_user_can_get_task_resource(): void
    {
        $task = $this->project->tasks()->create([
            'title' => 'Project Task',
            'user_id' => $this->project->user->id,
        ]);

        $this->getJson($task->path())
            ->assertOk()
            ->assertJson([
                'id' => $task->id,
                'title' => $task->title,
            ]);
    }

    /** @test */
    public function trashed_task_activity_request_returns_task_not_active_message(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $task->delete();

        $this->putJson(route('tasks.update', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]), [
            'title' => 'Updated title',
        ])->assertForbidden()->assertJson([
            'message' => 'Sorry, task is not active. Restore it to perform this activity.',
        ]);
    }

    /** @test */
    public function allowed_user_can_update_project_task(): void
    {
        $task = $this->project->addTask('test task');

        $updatedTitle = 'Task title updated';
        $updatedDescription = 'Task updated description';

        $status2 = TaskStatus::factory()->create();

        $this->withoutExceptionHandling()->putJson($task->path(), [
            'title' => $updatedTitle,
            'description' => $updatedDescription,
            'status_id' => $status2->id,
        ])->assertJsonPath('task.title', $updatedTitle);

        $task->refresh();

        $this->assertDatabaseHas('tasks', [
            'title' => $updatedTitle,
            'description' => $updatedDescription,
        ])
            ->assertEquals($task->status->id, $status2->id);
    }

    /** @test */
    public function user_cannot_update_task_when_project_is_abandoned(): void
    {
        $task = $this->project->addTask('test task');

        $this->project->delete();

        $this->putJson(route('tasks.update', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]), [
            'title' => 'Task title updated',
        ])->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'test task',
        ]);
    }

    /** @test */
    public function due_at_timezone_works_as_expected(): void
    {
        $this->user->update([
            'timezone' => 'Asia/Karachi',
        ]);

        $due_at = '2024-12-04T15:00:00';

        $task = $this->project->addTask('test task');

        $this->putJson($task->path(), [
            'due_at' => $due_at,
        ]);

        $expectedDueAt = Carbon::parse($due_at, $this->user->timezone)->setTimezone('UTC');

        $this->assertEquals($expectedDueAt->toDateTimeString(), $task->refresh()->due_at->toDateTimeString());
    }

    /** @test */
    public function task_policy_check(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $user = User::factory()->create();

        Sanctum::actingAs(
            $user,
        );

        $this->deleteJson(route('task.archive', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]))->assertForbidden();
    }

    /** @test */
    public function updating_due_date_resets_task_notification(): void
    {
        $task = Task::factory()->for($this->project)->create([
            'title' => 'Reminder task',
            'user_id' => $this->user->id,
            'due_at' => now()->addDay(),
            'notified' => '1 Day Before',
            'notify_sent' => true,
        ]);

        $this->putJson($task->path(), [
            'due_at' => now()->addDays(3)->format('Y-m-d\TH:i:s'),
        ])->assertOk();

        $this->assertEquals(0, (int) $task->fresh()->notify_sent);
    }

    /** @test */
    public function updating_notification_rule_resets_task_notification(): void
    {
        $task = Task::factory()->for($this->project)->create([
            'title' => 'Reminder task',
            'user_id' => $this->user->id,
            'due_at' => now()->addDay(),
            'notified' => '1 Day Before',
            'notify_sent' => true,
        ]);

        $this->putJson($task->path(), [
            'notified' => '5 Minutes Before',
            'due_at' => $task->due_at->format('Y-m-d\TH:i:s'),
        ])->assertOk();

        $this->assertEquals(0, (int) $task->fresh()->notify_sent);
    }
}
