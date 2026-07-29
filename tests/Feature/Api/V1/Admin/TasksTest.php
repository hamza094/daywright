<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\EnablesUserTwoFactor;

class TasksTest extends TestCase
{
    use EnablesUserTwoFactor;
    use RefreshDatabase;

    private User $admin;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAdminUser();
        $this->enableTwoFactorForUser($this->admin);

        Sanctum::actingAs($this->admin);
    }

    // Authorization

    #[Test]
    public function non_admin_cannot_access_tasks_index(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->getJson($this->apiV1AdminRoute('tasks.index'))
            ->assertForbidden();
    }

    #[Test]
    public function non_admin_cannot_bulk_delete_tasks(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $task = $this->createTask();

        $this->deleteJson($this->apiV1AdminRoute('tasks.bulk-delete'), ['task_ids' => [$task->id]])
            ->assertForbidden();
    }

    // Index & Filters

    #[Test]
    public function admin_can_list_tasks(): void
    {
        Task::factory()->count(3)->create();

        $this->getJson($this->apiV1AdminRoute('tasks.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta',
                'links',
            ]);
    }

    #[Test]
    public function returns_paginated_shape_when_no_tasks(): void
    {
        $response = $this->getJson($this->apiV1AdminRoute('tasks.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta',
                'links',
            ]);

        $this->assertSame([], $response->json('data'));
    }

    #[Test]
    public function can_search_tasks_by_project_name(): void
    {
        $project = $this->createProject(['name' => 'Unique Searchable Project']);
        $this->createTask(['project_id' => $project->id]);
        $this->createTask(); // different project

        $response = $this->getJson($this->tasksUrl(['search' => 'Unique Searchable']))
            ->assertOk();

        $tasks = $response->json('data');
        $this->assertCount(1, $tasks);
    }

    #[Test]
    public function task_search_treats_sql_wildcards_as_literals(): void
    {
        $literalProject = $this->createProject(['name' => 'Client% Migration']);
        $this->createTask(['project_id' => $literalProject->id]);

        $wildcardProject = $this->createProject(['name' => 'ClientX Migration']);
        $this->createTask(['project_id' => $wildcardProject->id]);

        $response = $this->getJson($this->tasksUrl(['search' => 'Client%']))
            ->assertOk();

        $projectNames = collect($response->json('data'))->pluck('project.name')->all();

        $this->assertSame([$literalProject->name], $projectNames);
    }

    #[Test]
    public function can_filter_tasks_by_active_and_trashed(): void
    {
        Task::factory()->create();
        $trashed = Task::factory()->create();
        $trashed->delete();

        // Active filter
        $active = $this->getJson($this->tasksUrl(['state' => 'active']))->assertOk();
        $this->assertNotEmpty($active->json('data'));

        // Trashed filter
        $trashedResponse = $this->getJson($this->tasksUrl(['state' => 'trashed']))->assertOk();
        $this->assertNotEmpty($trashedResponse->json('data'));
    }

    #[Test]
    public function rejects_legacy_top_level_filter_aliases(): void
    {
        $this->getJson($this->apiV1AdminRoute('tasks.index', query: [
            'search' => 'Unique Searchable',
            'state' => 'active',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search', 'state']);
    }

    #[Test]
    public function rejects_unsupported_top_level_query_parameters(): void
    {
        $this->getJson($this->apiV1AdminRoute('tasks.index', query: [
            'random' => 'value',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['random']);
    }

    #[Test]
    public function tasks_include_related_resources(): void
    {
        Task::factory()->create();

        $response = $this->getJson($this->apiV1AdminRoute('tasks.index'))
            ->assertOk();

        $firstTask = $response->json('data.0');
        $this->assertArrayHasKey('project', $firstTask);
        $this->assertArrayHasKey('status', $firstTask);
        $this->assertArrayHasKey('owner', $firstTask);
    }

    #[Test]
    public function tasks_are_paginated(): void
    {
        Task::factory()->count(55)->create();

        $response = $this->getJson($this->tasksUrl(params: ['per_page' => 10]))
            ->assertOk();

        $tasks = $response->json('data');
        $this->assertCount(10, $tasks);
    }

    #[Test]
    public function validates_filter_and_search_params(): void
    {
        $this->getJson($this->tasksUrl(['state' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter.state');

        $this->getJson($this->tasksUrl(['search' => str_repeat('a', 256)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter.search');
    }

    #[Test]
    public function validates_sort_param(): void
    {
        $this->getJson($this->tasksUrl(params: ['sort' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    #[Test]
    public function rejects_unknown_filter_keys(): void
    {
        $this->getJson($this->apiV1AdminRoute('tasks.index', query: [
            'filter' => ['owner' => 'admin'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter');
    }

    #[Test]
    public function rejects_unsupported_spatie_package_parameters(): void
    {
        $this->getJson($this->apiV1AdminRoute('tasks.index', query: [
            'include' => 'project',
            'fields' => ['tasks' => 'id,title'],
            'append' => 'foo',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['include', 'fields', 'append']);
    }

    #[Test]
    public function can_sort_tasks_by_title(): void
    {
        $alphaTask = $this->createTask(['title' => 'Alpha Task']);
        $zuluTask = $this->createTask(['title' => 'Zulu Task']);

        $response = $this->getJson($this->tasksUrl(params: ['sort' => 'title']))
            ->assertOk();

        $this->assertSame($alphaTask->id, $response->json('data.0.id'));
        $this->assertContains($zuluTask->id, collect($response->json('data'))->pluck('id')->all());
    }

    #[Test]
    public function tasks_index_defaults_to_newest_first_when_sort_is_omitted(): void
    {
        $oldTask = $this->createTask([
            'title' => 'Old Task',
            'created_at' => now()->subDays(3),
        ]);
        $newTask = $this->createTask([
            'title' => 'New Task',
            'created_at' => now(),
        ]);

        $response = $this->getJson($this->apiV1AdminRoute('tasks.index'))
            ->assertOk();

        $taskIds = collect($response->json('data'))->pluck('id')->take(2)->all();

        $this->assertSame([$newTask->id, $oldTask->id], $taskIds);
    }

    // Bulk Delete

    #[Test]
    public function admin_can_bulk_delete_tasks(): void
    {
        /** @var \Illuminate\Support\Collection<int, Task> $tasks */
        $tasks = Task::factory()->count(3)->create();
        $ids = $tasks->pluck('id')->toArray();

        $this->assertDatabaseHas('activities', [
            'subject_type' => Task::class,
            'subject_id' => $ids[0],
        ]);

        $this->deleteJson($this->apiV1AdminRoute('tasks.bulk-delete'), ['task_ids' => $ids])
            ->assertOk()
            ->assertJsonPath('message', 'Tasks deleted successfully.');

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('tasks', ['id' => $id]);
            $this->assertDatabaseMissing('activities', [
                'subject_type' => Task::class,
                'subject_id' => $id,
            ]);
        }
    }

    #[Test]
    public function bulk_delete_tasks_creates_audit_log(): void
    {
        /** @var \Illuminate\Support\Collection<int, Task> $tasks */
        $tasks = Task::factory()->count(2)->create();
        $ids = $tasks->pluck('id')->toArray();

        $this->deleteJson($this->apiV1AdminRoute('tasks.bulk-delete'), ['task_ids' => $ids])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'api_token',
            'actor_id' => $this->admin->id,
            'event' => 'destruction.bulk_tasks_deleted',
        ]);

        $log = AuditLog::where('event', 'destruction.bulk_tasks_deleted')->first();

        $this->assertNotNull($log);
        $this->assertCount(2, $log->old_values['task_ids']);
        $this->assertSame(2, $log->old_values['count']);
        $this->assertTrue($log->metadata['bulk_operation']);
        $this->assertNotNull($log->created_at);
    }

    #[Test]
    public function bulk_delete_detaches_assignees_before_deleting(): void
    {
        $task = $this->createTask();
        $assignee = $this->createUser();
        $task->assignee()->attach($assignee->id);

        $this->assertDatabaseHas('task_user', [
            'task_id' => $task->id,
            'user_id' => $assignee->id,
        ]);

        $this->deleteJson($this->apiV1AdminRoute('tasks.bulk-delete'), ['task_ids' => [$task->id]])
            ->assertOk();

        $this->assertDatabaseMissing('task_user', [
            'task_id' => $task->id,
            'user_id' => $assignee->id,
        ]);
    }

    #[Test]
    public function bulk_delete_validates_task_ids(): void
    {
        $this->deleteJson($this->apiV1AdminRoute('tasks.bulk-delete'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('task_ids');

        $this->deleteJson($this->apiV1AdminRoute('tasks.bulk-delete'), ['task_ids' => [99999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('task_ids.0');

        $task = $this->createTask();

        $this->deleteJson($this->apiV1AdminRoute('tasks.bulk-delete'), [
            'task_ids' => [$task->id, $task->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('task_ids.0');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $params
     */
    private function tasksUrl(array $filters = [], array $params = []): string
    {
        $query = $params;

        if ($filters !== []) {
            $query['filter'] = $filters;
        }

        return $this->apiV1AdminRoute('tasks.index', query: $query);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(array $attributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->create($attributes);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createAdminUser(array $attributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->admin()->create($attributes);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTask(array $attributes = []): Task
    {
        /** @var Task $task */
        $task = Task::factory()->create($attributes);

        return $task;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createProject(array $attributes = []): Project
    {
        /** @var Project $project */
        $project = Project::factory()->create($attributes);

        return $project;
    }
}
