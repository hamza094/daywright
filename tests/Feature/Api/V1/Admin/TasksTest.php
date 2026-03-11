<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TasksTest extends TestCase
{
    use RefreshDatabase;

    private const string TASKS_ROUTE = '/api/v1/admin/tasks';

    private const string BULK_DELETE_ROUTE = '/api/v1/admin/tasks/bulk-delete';

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

        $this->getJson(self::TASKS_ROUTE)
            ->assertForbidden();
    }

    #[Test]
    public function non_admin_cannot_bulk_delete_tasks(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        /** @var Task $task */
        $task = $this->createTask();

        $this->deleteJson(self::BULK_DELETE_ROUTE, ['task_ids' => [$task->id]])
            ->assertForbidden();
    }

    // Index & Filters

    #[Test]
    public function admin_can_list_tasks(): void
    {
        Task::factory()->count(3)->create();

        $response = $this->getJson(self::TASKS_ROUTE)
            ->assertOk();

        $this->assertNotEmpty($response->json('data'));
    }

    #[Test]
    public function returns_empty_message_when_no_tasks(): void
    {
        $this->getJson(self::TASKS_ROUTE)
            ->assertOk()
            ->assertJsonPath('message', 'Sorry no related tasks found');
    }

    #[Test]
    public function can_search_tasks_by_project_name(): void
    {
        /** @var Project $project */
        $project = $this->createProject(['name' => 'Unique Searchable Project']);
        $this->createTask(['project_id' => $project->id]);
        $this->createTask(); // different project

        $response = $this->getJson(self::TASKS_ROUTE.'?search=Unique Searchable')
            ->assertOk();

        $tasks = $response->json('data');
        $this->assertCount(1, $tasks);
    }

    #[Test]
    public function can_filter_tasks_by_active_and_trashed(): void
    {
        Task::factory()->create();
        $trashed = Task::factory()->create();
        $trashed->delete();

        // Active filter
        $active = $this->getJson(self::TASKS_ROUTE.'?filter=active')->assertOk();
        $this->assertNotEmpty($active->json('data'));

        // Trashed filter
        $trashedResponse = $this->getJson(self::TASKS_ROUTE.'?filter=trashed')->assertOk();
        $this->assertNotEmpty($trashedResponse->json('data'));
    }

    #[Test]
    public function tasks_include_related_resources(): void
    {
        Task::factory()->create();

        $response = $this->getJson(self::TASKS_ROUTE)
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

        $response = $this->getJson(self::TASKS_ROUTE)
            ->assertOk();

        $tasks = $response->json('data');
        $this->assertCount(50, $tasks);
    }

    #[Test]
    public function validates_filter_and_search_params(): void
    {
        $this->getJson(self::TASKS_ROUTE.'?filter=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter');

        $this->getJson(self::TASKS_ROUTE.'?search='.str_repeat('a', 256))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('search');
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

        $this->deleteJson(self::BULK_DELETE_ROUTE, ['task_ids' => $ids])
            ->assertOk()
            ->assertJsonPath('message', 'Tasks deleted Successfully');

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('tasks', ['id' => $id]);
            $this->assertDatabaseMissing('activities', [
                'subject_type' => Task::class,
                'subject_id' => $id,
            ]);
        }
    }

    #[Test]
    public function bulk_delete_detaches_assignees_before_deleting(): void
    {
        /** @var Task $task */
        $task = $this->createTask();
        /** @var User $assignee */
        $assignee = $this->createUser();
        $task->assignee()->attach($assignee->id);

        $this->assertDatabaseHas('task_user', [
            'task_id' => $task->id,
            'user_id' => $assignee->id,
        ]);

        $this->deleteJson(self::BULK_DELETE_ROUTE, ['task_ids' => [$task->id]])
            ->assertOk();

        $this->assertDatabaseMissing('task_user', [
            'task_id' => $task->id,
            'user_id' => $assignee->id,
        ]);
    }

    #[Test]
    public function bulk_delete_validates_task_ids(): void
    {
        $this->deleteJson(self::BULK_DELETE_ROUTE, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('task_ids');

        $this->deleteJson(self::BULK_DELETE_ROUTE, ['task_ids' => [99999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('task_ids.0');

        /** @var Task $task */
        $task = $this->createTask();

        $this->deleteJson(self::BULK_DELETE_ROUTE, [
            'task_ids' => [$task->id, $task->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('task_ids.0');
    }

    private function enableTwoFactorForUser(User $user): void
    {
        $user->createTwoFactorAuth();

        $user->enableTwoFactorAuth();
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
