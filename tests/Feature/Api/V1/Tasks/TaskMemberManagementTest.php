<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class TaskMemberManagementTest extends TestCase
{
    use ProjectSetup;
    use RefreshDatabase;

    /** @test */
    public function members_assign_to_task_and_pervent_duplication(): void
    {
        Notification::fake();

        $task = $this->project->addTask('test task');

        $user = User::factory()->create();
        $expectedLink = $this->apiV1Route('projects.show', ['project' => $this->project]);
        $expectedUrl = route('api.v1.projects.show', ['project' => $this->project]);

        $members = [$user->id];

        $user->members()->syncWithoutDetaching([
            $this->project->id => ['active' => true],
        ]);

        $this->assignMembersToTask($task, $members)
            ->assertSuccessful()
            ->assertJson([
                'message' => 'Task assigned successfully.',
            ]);

        $this->assertDatabaseHas('task_user', [
            'task_id' => $task->id,
            'user_id' => $user->id,
        ]);

        Notification::assertSentTo($user, TaskAssigned::class, fn (TaskAssigned $notification): bool => $notification->toArray($user)['link'] === $expectedLink
            && $notification->toMail($user)->actionUrl === $expectedUrl);
        Notification::assertNotSentTo($this->user, TaskAssigned::class);

        $this->assignMembersToTask($task, $members)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['members' => 'One or more users are already assigned to the task.']);
    }

    /** @test */
    public function members_cannot_assign_task_when_project_is_abandoned(): void
    {
        $task = $this->project->addTask('test task');

        $user = User::factory()->create();

        $this->project->members()->attach($user->id, ['active' => true]);

        $this->project->delete();

        $this->assignMembersToTask($task, [$user->id])
            ->assertConflict()
            ->assertJsonPath('message', 'Project is archived. Restore it before performing this action.')
            ->assertJsonPath('code', 'project_archived');

        $this->assertDatabaseMissing('task_user', [
            'task_id' => $task->id,
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function it_unassigns_a_member_from_a_task_and_handles_invalid_requests(): void
    {
        $task = $this->project->addTask('test task');
        $task->assignee()->attach($this->user);

        $this->unassignMemberFromTask($task, $this->user->id)
            ->assertSuccessful()
            ->assertJson(['message' => 'Task member unassigned.']);

        $this->assertDatabaseMissing('task_user', [
            'task_id' => $task->id,
            'user_id' => $this->user->id,
        ]);

        $this->unassignMemberFromTask($task, $this->user->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['member' => 'The selected user is not a current member of task.']);
    }

    /** @test */
    public function project_members_does_not_perform_task_operations(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $user = User::factory()->create();

        $this->project->activeMembers()->attach($user);

        Sanctum::actingAs($user);

        $this->assertFalse($user->can('taskaccess', $task));
    }

    /** @test */
    public function auth_user_can_search_project_members(): void
    {
        $user = User::factory()->create(['name' => 'test_user']);

        $task = Task::factory()->create(['project_id' => $this->project->id]);
        $task->assignee()->detach();

        $this->project->members()->attach($user->id, ['active' => true]);

        $response = $this->withoutExceptionHandling()->getJson(route('api.v1.task.members.search', [
            'project' => $this->project->slug,
            'task' => $task->id,
            'search' => 'test',
        ]))->assertSuccessful();

        $payload = $response->json();

        $this->assertCount(1, $payload);
    }

    /** @test */
    public function search_project_members_requires_a_search_term(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);

        $this->getJson(route('api.v1.task.members.search', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('search');
    }

    protected function assignMembersToTask(Task $task, array $members)
    {
        return $this->withHeaders($this->idempotencyHeaders())->patchJson(route('api.v1.task.assign', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]), ['members' => $members]);
    }

    protected function unassignMemberFromTask(Task $task, int $memberId)
    {
        return $this->withHeaders($this->idempotencyHeaders())->patchJson(route('api.v1.task.unassign', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]), ['member' => $memberId]);
    }
}
