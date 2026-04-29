<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class TaskFeaturesTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;
    /**
     * Feature test.
     */

    /** @test */
    public function members_assign_to_task_and_pervent_duplication(): void
    {
        $task = $this->project->addTask('test task');

        $user = User::factory()->create();

        $members = [$user->id];

        $user->members()->syncWithoutDetaching([
            $this->project->id => ['active' => true]]);

        $this->assignMembersToTask($task, $members)
            ->assertSuccessful()
            ->assertJson([
                'message' => 'Task assigned to member Successfully',
            ]);

        $this->assertDatabaseHas('task_user', [
            'task_id' => $task->id,
            'user_id' => $user->id,
        ]);

        $this->assertCount(1, $user->notifications);

        // Attempt to reassign the same member to the task
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

        $this->assignMembersToTask($task, [$user->id])->assertForbidden();

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
            ->assertJson(['message' => 'Task member Unassigned']);

        $this->assertDatabaseMissing('task_user', [
            'task_id' => $task->id,
            'user_id' => $this->user->id,
        ]);

        $this->unassignMemberFromTask($task, $this->user->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['member' => 'The selected user is not a current member of task.']);
    }

    /** @test */
    public function allowed_user_can_archive_and_unarchive_task(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->patchJson(route('task.archive', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]));

        $this->assertSoftDeleted($task);

        $this->patchJson(route('task.unarchive', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]));

        $this->assertNotSoftDeleted($task);
    }

    /** @test */
    public function project_members_does_not_perform_task_operations(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $user = User::factory()->create();

        $this->project->activeMembers()->attach($user);

        Sanctum::actingAs(
            $user,
        );

        $this->assertFalse($user->can('taskaccess', $task));
    }

    /** @test */
    public function auth_user_can_search_project_members(): void
    {
        $user = User::factory()->create(['name' => 'test_user']);

        $task = Task::factory()->create(['project_id' => $this->project->id]);
        // Ensure no pre-attached assignees exclude our user from search results
        $task->assignee()->detach();

        $this->project->members()->attach($user->id, ['active' => true]);

        $response = $this->withoutExceptionHandling()->getJson(route('task.members.search', [
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

        $this->getJson(route('task.members.search', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('search');
    }

    /** @test */
    public function allowed_user_can_remove_archived_task_from_database(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->patchJson(route('task.archive', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]));

        $this->assertSoftDeleted($task);

        $this->deleteJson(route('task.remove', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]))->assertNoContent();

        $this->assertModelMissing($task);
    }

    /** @test */
    public function active_task_cannot_be_removed_until_it_is_archived(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->deleteJson(route('task.remove', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]))
            ->assertForbidden()
            ->assertJson([
                'message' => 'Task must be trashed to perform this action',
            ]);

        $this->assertModelExists($task);
    }

    protected function assignMembersToTask(Task $task, array $members)
    {
        return $this->patchJson(route('task.assign', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]), ['members' => $members]);
    }

    protected function unassignMemberFromTask(Task $task, int $memberId)
    {
        return $this->patchJson(route('task.unassign', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]), ['member' => $memberId]);
    }
}
