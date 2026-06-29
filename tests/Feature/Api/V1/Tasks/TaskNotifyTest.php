<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Tasks;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Notifications\TaskDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class TaskNotifyTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function test_task_notify_command_handles_notifications(): void
    {
        Notification::fake();

        $status = $this->createTaskStatus();

        $user = $this->createUser();

        $task = $this->createTask([
            'notified' => '1 Day Before',
            'due_at' => now()->addDay(),
            'status_id' => $status->id,
        ]);

        $task->assignee()->attach($user);
        $expectedLink = $this->apiV1Route('projects.show', ['project' => $task->project]);
        $expectedUrl = route('api.v1.projects.show', ['project' => $task->project]);

        $this->artisan('tasks:notify')
            ->expectsOutput('Task notifications sent successfully.')
            ->assertSuccessful();

        Notification::assertSentTo($user, TaskDue::class, fn (TaskDue $notification): bool => $notification->toArray($user)['link'] === $expectedLink
            && $notification->toMail($user)->actionUrl === $expectedUrl);

        $this->assertEquals($task->fresh()->notify_sent, 1);
    }

    /** @test */
    public function test_task_notify_command_skips_tasks_belonging_to_trashed_projects(): void
    {
        Notification::fake();

        $status = $this->createTaskStatus();

        $user = $this->createUser();

        $task = $this->createTask([
            'notified' => '1 Day Before',
            'due_at' => now()->addDay(),
            'status_id' => $status->id,
        ]);

        $task->assignee()->attach($user);
        $task->project->delete();

        $this->artisan('tasks:notify')
            ->expectsOutput('Task notifications sent successfully.')
            ->assertSuccessful();

        Notification::assertNotSentTo($user, TaskDue::class);

        $this->assertEquals(0, (int) $task->fresh()->notify_sent);
    }

    /** @test */
    public function task_notify_command_is_safe_to_repeat(): void
    {
        Notification::fake();

        $status = $this->createTaskStatus();

        $user = $this->createUser();

        $task = $this->createTask([
            'notified' => '1 Day Before',
            'due_at' => now()->addDay(),
            'status_id' => $status->id,
        ]);

        $task->assignee()->attach($user);

        $this->artisan('tasks:notify')->assertSuccessful();
        $this->artisan('tasks:notify')->assertSuccessful();

        Notification::assertSentToTimes($user, TaskDue::class, 1);
        $this->assertEquals(1, (int) $task->fresh()->notify_sent);
    }

    /** @test */
    public function task_notify_command_processes_tasks_in_chunks(): void
    {
        Notification::fake();

        $status = $this->createTaskStatus();

        $user = $this->createUser();

        // Create 120 tasks (more than chunk size of 50)
        $tasks = Task::factory()
            ->count(120)
            ->for($this->project)
            ->create([
                'notified' => '1 Day Before',
                'due_at' => now()->addDay(),
                'status_id' => $status->id,
            ]);

        foreach ($tasks as $task) {
            $task->assignee()->attach($user);
        }

        $this->artisan('tasks:notify')->assertSuccessful();

        // All tasks should have notify_sent set
        $this->assertEquals(120, Task::where('notify_sent', true)->count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTaskStatus(array $attributes = []): TaskStatus
    {
        /** @var TaskStatus $status */
        $status = TaskStatus::factory()->create($attributes);

        return $status;
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
    private function createTask(array $attributes = []): Task
    {
        /** @var Task $task */
        $task = Task::factory()->create($attributes);

        return $task;
    }
}
