<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Tasks;

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

        $this->getJson($this->apiV1ProjectRoute('tasks.index', $this->project, query: [
            'filter' => ['state' => 'archived'],
        ]))
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
    }

    /** @test */
    public function allowed_user_see_archived_tasks_via_legacy_request_alias(): void
    {
        Task::factory(['deleted_at' => now()])
            ->count(2)
            ->for($this->project)
            ->create();

        $this->getJson($this->apiV1ProjectRoute('tasks.index', $this->project, query: [
            'filter' => ['state' => 'archived'],
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    /** @test */
    public function task_index_rejects_invalid_legacy_request_alias_values(): void
    {
        $this->getJson($this->apiV1ProjectRoute('tasks.index', $this->project, query: [
            'request' => 'previous',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['request']);
    }

    /** @test */
    public function archived_tasks_can_limit_results_per_page(): void
    {
        Task::factory(['deleted_at' => now()])
            ->count(5)
            ->for($this->project)
            ->create();

        $this->getJson($this->apiV1ProjectRoute('tasks.index', $this->project, query: [
            'filter' => ['state' => 'archived'],
            'per_page' => 2,
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);
    }

    /** @test */
    public function task_index_validates_invalid_state_filter(): void
    {
        $this->getJson($this->apiV1ProjectRoute('tasks.index', $this->project, query: [
            'filter' => ['state' => 'invalid'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter.state');
    }

    /** @test */
    public function task_index_rejects_unsupported_nested_filter_keys(): void
    {
        $this->getJson($this->apiV1ProjectRoute('tasks.index', $this->project, query: [
            'filter' => ['status' => 'archived'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter');
    }

    /** @test */
    public function task_index_rejects_unsupported_top_level_query_parameters(): void
    {
        $this->getJson($this->apiV1ProjectRoute('tasks.index', $this->project, query: [
            'sort' => '-created_at',
            'include' => 'passwords',
            'random' => 'value',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'include', 'random']);
    }

    /** @test */
    public function allowed_user_see_active_tasks_and_paginate(): void
    {
        Task::factory()
            ->count(9)
            ->for($this->project)
            ->create();

        $response = $this->getJson($this->apiV1ProjectRoute('tasks.index', $this->project))
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
            $this->assertStringStartsWith($this->apiV1ProjectRoute('tasks.index', $this->project).'/', $path);
        });
    }

    /** @test */
    public function task_requires_a_title(): void
    {
        $task = Task::factory()->make(['title' => null, 'project_id' => $this->project->id]);

        $this->postJson($this->apiV1ProjectRoute('tasks.store', $this->project), $task->toArray())->assertJsonValidationErrors('title');
    }

    /** @test */
    public function allowed_user_can_create_projects_task(): void
    {
        $this->postJson($this->apiV1ProjectRoute('tasks.store', $this->project), [
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

        $this->postJson(route('api.v1.tasks.store', ['project' => $this->project->slug]), [
            'title' => 'Blocked Task',
            'status_id' => $this->status->id,
        ])->assertConflict()
            ->assertJsonPath('message', 'Project is archived. Restore it before performing this action.')
            ->assertJsonPath('code', 'project_archived');

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

        $this->postJson($this->apiV1ProjectRoute('tasks.store', $this->project), [
            'title' => 'Project Task',
            'status_id' => $this->status->id,
        ])->assertJsonValidationErrors('title');

        $project2 = Project::factory()->for($this->user)->create();

        $this->postJson($this->apiV1ProjectRoute('tasks.store', $project2),
            ['title' => 'Project Task'])
            ->assertCreated();
    }

    /** @test */
    public function task_limit_per_project(): void
    {
        $taskLimit = (int) config('plan-limits.free.max_tasks_per_project', 10);

        Task::factory()->count($taskLimit)
            ->for($this->project)->create();

        $this->postJson($this->apiV1ProjectRoute('tasks.store', $this->project),
            ['title' => 'Project Task'])
            ->assertForbidden();
    }

    /** @test */
    public function allowed_user_can_get_task_resource(): void
    {
        $task = $this->project->tasks()->create([
            'title' => 'Project Task',
            'user_id' => $this->project->user->id,
            'due_at' => Carbon::create(2026, 5, 1, 12, 30, 0, 'UTC'),
        ]);

        $this->getJson($this->apiV1ProjectTaskRoute('tasks.show', $this->project, $task))
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

        $this->putJson(route('api.v1.tasks.update', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]), [
            'title' => 'Updated title',
        ])->assertConflict()
            ->assertJsonPath('message', 'Task is archived. Restore it before performing this action.')
            ->assertJsonPath('code', 'task_archived');
    }

    /** @test */
    public function allowed_user_can_update_project_task(): void
    {
        $task = $this->project->addTask('test task');

        $updatedTitle = 'Task title updated';
        $updatedDescription = 'Task updated description';

        $status2 = TaskStatus::factory()->create();

        $this->withoutExceptionHandling()->putJson($this->apiV1ProjectTaskRoute('tasks.update', $this->project, $task), [
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
    public function project_member_cannot_update_another_users_task(): void
    {
        $task = $this->project->addTask('test task');
        $member = User::factory()->create();

        $this->project->members()->attach($member->id, ['active' => true]);

        Sanctum::actingAs($member);

        $this->putJson($this->apiV1ProjectTaskRoute('tasks.update', $this->project, $task), [
            'title' => 'Unauthorized update',
        ])->assertForbidden()
            ->assertJsonPath('message', "Only Project's owner and task owner are allowed to access this feature.")
            ->assertJsonPath('code', 'forbidden');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'test task',
        ]);
    }

    /** @test */
    public function user_cannot_update_task_when_project_is_abandoned(): void
    {
        $task = $this->project->addTask('test task');

        $this->project->delete();

        $this->putJson(route('api.v1.tasks.update', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]), [
            'title' => 'Task title updated',
        ])->assertConflict()
            ->assertJsonPath('message', 'Project is archived. Restore it before performing this action.')
            ->assertJsonPath('code', 'project_archived');

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

        $this->putJson($this->apiV1ProjectTaskRoute('tasks.update', $this->project, $task), [
            'due_at' => $dueAt,
        ])->assertOk();

        $expectedDueAt = Carbon::parse($dueAt)->setTimezone('UTC');

        $this->assertEquals($expectedDueAt->toDateTimeString(), $task->refresh()->due_at->toDateTimeString());
    }

    /** @test */
    public function due_at_must_be_iso_8601_with_timezone_offset(): void
    {
        $task = $this->project->addTask('test task');

        $this->putJson($this->apiV1ProjectTaskRoute('tasks.update', $this->project, $task), [
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

        $this->patchJson(route('api.v1.task.archive', [
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

        $this->putJson($this->apiV1ProjectTaskRoute('tasks.update', $this->project, $task), [
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

        $this->putJson($this->apiV1ProjectTaskRoute('tasks.update', $this->project, $task), [
            'notified' => '5 Minutes Before',
            'due_at' => $task->due_at->toIso8601String(),
        ])->assertOk();

        $this->assertEquals(0, (int) $task->fresh()->notify_sent);
    }
}
