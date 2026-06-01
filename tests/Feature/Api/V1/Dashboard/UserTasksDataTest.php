<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Dashboard;

use App\Enums\TaskStatus as TaskStatusEnum;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Override;
use Tests\TestCase;

class UserTasksDataTest extends TestCase
{
    use RefreshDatabase;

    public $project;

    public $user;

    public $status;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'johndoe@example.org',
            'password' => Hash::make('testpassword'),
        ]);

        Sanctum::actingAs(
            $this->user,
        );

        $this->status = TaskStatus::factory()->create();

        $this->project = Project::factory()->for($this->user)->create();

        // if ($this instanceof \Tests\Feature\TaskTest) {
        // $this->status = TaskStatus::factory()->create();
        // }

        $middlewaresToRemove = [
            \App\Http\Middleware\CheckSubscription::class,
        ];

        $this->withoutMiddleware($middlewaresToRemove);

        // Seed canonical TaskStatus rows needed by these tests
        TaskStatus::query()->firstOrCreate(
            ['id' => TaskStatusEnum::IN_PROGRESS],
            ['label' => 'In Progress', 'color' => '#0000FF', 'user_id' => $this->user->id]
        );
        TaskStatus::query()->firstOrCreate(
            ['id' => TaskStatusEnum::COMPLETED],
            ['label' => 'Completed', 'color' => '#00FF00', 'user_id' => $this->user->id]
        );
    }

    /** @test */
    public function auth_user_can_view_tasks_with_user_created_and_task_assigned_filters(): void
    {
        // Create tasks for the authenticated user
        Task::factory(['user_id' => $this->user->id, 'project_id' => $this->project->id])->count(3)->create();

        // Create a task by another user but assigned to authenticated user
        $randomUser = User::factory()->create();
        $assignedTask = Task::factory(['user_id' => $randomUser->id])->create();
        $assignedTask->assignee()->attach($this->user);

        $response = $this->getJson($this->dashboardTasksUrl([
            'task_assigned' => 1,
            'user_created' => 1,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [],
                'meta' => [
                    'applied_filters',
                    'total',
                ],
            ]);

        $responseData = $response->json();

        $this->assertEquals(['Filter by Created', 'Filter by Assigned'], $responseData['meta']['applied_filters']);
        $this->assertCount(4, $responseData['data']);
        $this->assertEquals(4, $responseData['meta']['total']);
        $this->assertEquals($assignedTask->title, $responseData['data'][3]['title']);
    }

    /** @test */
    public function auth_user_can_filter_tasks_by_user_created_only(): void
    {
        // Create tasks for the authenticated user
        Task::factory(['user_id' => $this->user->id, 'project_id' => $this->project->id])->count(2)->create();

        // Create a task by another user (should not appear)
        $randomUser = User::factory()->create();
        Task::factory(['user_id' => $randomUser->id])->create();

        $response = $this->getJson($this->dashboardTasksUrl(['user_created' => 1]));

        $response->assertOk();
        $responseData = $response->json();

        $this->assertEquals(['Filter by Created'], $responseData['meta']['applied_filters']);
        $this->assertCount(2, $responseData['data']);
        $this->assertEquals(2, $responseData['meta']['total']);
    }

    /** @test */
    public function auth_user_can_filter_tasks_by_task_assigned_only(): void
    {
        // Create a task by another user and assign to authenticated user
        $randomUser = User::factory()->create();
        $assignedTask = Task::factory(['user_id' => $randomUser->id])->create();
        $assignedTask->assignee()->attach($this->user);

        // Create a task by authenticated user (should not appear with task_assigned filter only)
        Task::factory(['user_id' => $this->user->id, 'project_id' => $this->project->id])->create();

        $response = $this->getJson($this->dashboardTasksUrl(['task_assigned' => 1]));

        $response->assertOk();
        $responseData = $response->json();

        $this->assertEquals(['Filter by Assigned'], $responseData['meta']['applied_filters']);
        $this->assertCount(1, $responseData['data']);
        $this->assertEquals(1, $responseData['meta']['total']);
        $this->assertEquals($assignedTask->title, $responseData['data'][0]['title']);
    }

    /** @test */
    public function auth_user_can_filter_tasks_by_completed_status(): void
    {
        // Create completed tasks
        Task::factory([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'status_id' => TaskStatusEnum::COMPLETED,
        ])->count(2)->create();

        // Create non-completed task
        Task::factory([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'status_id' => TaskStatusEnum::IN_PROGRESS,
        ])->create();

        $response = $this->withoutExceptionHandling()->getJson($this->dashboardTasksUrl([
            'user_created' => 1,
            'completed' => 1,
        ]));

        $response->assertOk();
        $responseData = $response->json();

        $this->assertEquals(['Filter by Created', 'Filter by Completed'], $responseData['meta']['applied_filters']);
        $this->assertCount(2, $responseData['data']);
        $this->assertEquals(2, $responseData['meta']['total']);
    }

    /** @test */
    public function auth_user_can_filter_tasks_by_overdue_status(): void
    {
        // Create overdue tasks
        Task::factory([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'due_at' => Carbon::yesterday(),
            'status_id' => TaskStatusEnum::IN_PROGRESS,
        ])->count(2)->create();

        // Create non-overdue task
        Task::factory([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'due_at' => Carbon::tomorrow(),
            'status_id' => TaskStatusEnum::IN_PROGRESS,
        ])->create();

        $response = $this->getJson($this->dashboardTasksUrl([
            'user_created' => 1,
            'overdue' => 1,
        ]));

        $response->assertOk();
        $responseData = $response->json();

        $this->assertEquals(['Filter by Created', 'Filter by Overdue'], $responseData['meta']['applied_filters']);
        $this->assertCount(2, $responseData['data']);
    }

    /** @test */
    public function auth_user_can_filter_tasks_by_remaining_status(): void
    {
        // Create remaining (not completed) tasks
        Task::factory([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'status_id' => TaskStatusEnum::IN_PROGRESS,
        ])->count(2)->create();

        // Create completed task
        Task::factory([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'status_id' => TaskStatusEnum::COMPLETED,
        ])->create();

        $response = $this->getJson($this->dashboardTasksUrl([
            'user_created' => 1,
            'remaining' => 1,
        ]));

        $response->assertOk();
        $responseData = $response->json();

        $this->assertEquals(['Filter by Created', 'Filter by Remaining'], $responseData['meta']['applied_filters']);
        $this->assertCount(2, $responseData['data']);
    }

    /** @test */
    public function request_requires_at_least_one_filter(): void
    {
        $response = $this->getJson('api/v1/dashboard/tasks');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filter']);
    }

    /** @test */
    public function request_with_false_values_requires_at_least_one_filter(): void
    {
        $response = $this->getJson($this->dashboardTasksUrl([
            'user_created' => 0,
            'completed' => 0,
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filter']);
    }

    /** @test */
    public function request_rejects_legacy_top_level_boolean_aliases(): void
    {
        $response = $this->getJson('api/v1/dashboard/tasks?'.http_build_query([
            'user_created' => 1,
            'completed' => 1,
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_created', 'completed']);
    }

    /** @test */
    public function request_rejects_unsupported_top_level_query_parameters(): void
    {
        $response = $this->getJson('api/v1/dashboard/tasks?'.http_build_query([
            'filter' => ['user_created' => 1],
            'sort' => 'created_at',
            'include' => 'project',
            'random' => 'value',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sort', 'include', 'random']);
    }

    /** @test */
    public function status_filters_without_user_context_default_to_user_tasks(): void
    {
        // Create overdue task by authenticated user
        Task::factory([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'due_at' => Carbon::yesterday(),
            'status_id' => TaskStatusEnum::IN_PROGRESS,
        ])->create();

        // Create overdue task by another user (should not appear)
        $otherUser = User::factory()->create();
        Task::factory([
            'user_id' => $otherUser->id,
            'due_at' => Carbon::yesterday(),
            'status_id' => TaskStatusEnum::IN_PROGRESS,
        ])->create();

        // Create overdue task assigned to authenticated user
        $assignedTask = Task::factory([
            'user_id' => $otherUser->id,
            'due_at' => Carbon::yesterday(),
            'status_id' => TaskStatusEnum::IN_PROGRESS,
        ])->create();
        $assignedTask->assignee()->attach($this->user);

        $response = $this->getJson($this->dashboardTasksUrl(['overdue' => 1]));

        $response->assertOk();
        $responseData = $response->json();

        // Should return 2 tasks: 1 created by user + 1 assigned to user
        $this->assertCount(2, $responseData['data']);
        $this->assertEquals(2, $responseData['meta']['total']);
    }

    /**
     * @param  array<string, int|bool>  $filters
     */
    private function dashboardTasksUrl(array $filters = []): string
    {
        return 'api/v1/dashboard/tasks'.($filters === [] ? '' : '?'.http_build_query(['filter' => $filters]));
    }
}
