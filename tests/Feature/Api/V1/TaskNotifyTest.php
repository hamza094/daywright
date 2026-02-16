<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Notifications\TaskDue;
use App\Traits\ProjectSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskNotifyTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    /** @test */
    public function test_task_notify_command_handles_notifications(): void
    {
        Notification::fake();

        $status = TaskStatus::factory()->create();

        $user = User::factory()->create();

        $task = Task::factory()->create([
            'notified' => '1 Day Before',
            'due_at' => now()->addDay(),
            'status_id' => $status->id,
        ]);

        $task->assignee()->attach($user);

        $this->artisan('tasks:notify')
            ->expectsOutput('Task notifications sent successfully.')
            ->assertSuccessful();

        Notification::assertSentTo($user, TaskDue::class);

        $this->assertEquals($task->fresh()->notify_sent, 1);
    }

    /** @test */
    public function test_task_notify_command_skips_tasks_belonging_to_trashed_projects(): void
    {
        Notification::fake();

        $status = TaskStatus::factory()->create();

        $user = User::factory()->create();

        $task = Task::factory()->create([
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
}
