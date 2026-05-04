<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

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

        $this->getJson($this->project->path().'/tasks?'.http_build_query([
            'filter' => ['state' => 'archived'],
        ]))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data']);
    }

    /** @test */
    public function task_index_validates_invalid_state_filter(): void
    {
        $this->getJson($this->project->path().'/tasks?'.http_build_query([
            'filter' => ['state' => 'invalid'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter.state');
    }

    /** @test */
    public function allowed_user_see_active_tasks_and_paginate(): void
    {
        Task::factory()
            ->count(9)
            ->for($this->project)
            ->create();

        $response = $this->getJson($this->project->path().'/tasks')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'links' => ['self'],
                    ],
                ],
                'links',
                'meta',
            ]);

        collect($response->json('data'))->pluck('links.self')->each(function (?string $path): void {
            $this->assertNotNull($path);
            $this->assertStringStartsWith($this->project->path().'/tasks/', $path);
        });
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
            ->assertJsonPath('data.id', 1)
            ->assertJsonPath('data.title', 'My Project Task');

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
            'due_at' => Carbon::create(2026, 5, 1, 12, 30, 0, 'UTC'),
        ]);

        $this->getJson($task->path())
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.title', $task->title)
            ->assertJsonPath('data.due_at', $task->due_at?->setTimezone('UTC')->toIso8601String())
            ->assertJsonPath('data.created_at', $task->created_at?->setTimezone('UTC')->toIso8601String());
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
        ])->assertJsonPath('data.title', $updatedTitle);

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

        $dueAt = Carbon::create(2024, 12, 4, 15, 0, 0, 'Asia/Karachi')->toIso8601String();

        $task = $this->project->addTask('test task');

        $this->putJson($task->path(), [
            'due_at' => $dueAt,
        ])->assertOk();

        $expectedDueAt = Carbon::parse($dueAt)->setTimezone('UTC');

        $this->assertEquals($expectedDueAt->toDateTimeString(), $task->refresh()->due_at->toDateTimeString());
    }

    /** @test */
    public function due_at_must_be_iso_8601_with_timezone_offset(): void
    {
        $task = $this->project->addTask('test task');

        $this->putJson($task->path(), [
            'due_at' => '2024-12-04T15:00:00',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('due_at');
    }

    /** @test */
    public function task_policy_check(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $user = User::factory()->create();

        Sanctum::actingAs(
            $user,
        );

        $this->patchJson(route('task.archive', [
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
            'due_at' => now()->addDays(3)->toIso8601String(),
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
            'due_at' => $task->due_at->toIso8601String(),
        ])->assertOk();

        $this->assertEquals(0, (int) $task->fresh()->notify_sent);
    }
}
