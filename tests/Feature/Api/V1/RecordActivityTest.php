<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Task;
use App\Models\User;
use App\Traits\ProjectSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

use function Safe\json_encode;

class RecordActivityTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    // Since the project is automatically with ProjectSetup trait it should already have one activity

    /** @test */
    public function creating_a_project(): void
    {

        $this->assertDatabaseHas('activities', [
            'project_id' => $this->project->id,
            'description' => 'created_project',
        ]);

        /** @var \App\Models\Activity $activity */
        $activity = $this->project->activities()->latest()->first();

        $this->assertEquals('created_project', $activity->description);
    }

    /** @test */
    public function record_activity_on_updating_a_project(): void
    {
        $initialAttributes = $this->project->only(['name', 'slug']);

        $this->project->update(['name' => 'changed name']);

        $this->assertEquals(2, $this->project->activities()->count());

        /** @var \App\Models\Activity $activity */
        $activity = $this->project->activities()->first();

        $this->assertEquals('updated_project', $activity->description);

        $this->assertEquals([
            'before' => ['name' => $initialAttributes['name'], 'slug' => $initialAttributes['slug']],
            'after' => ['name' => 'changed name', 'slug' => $this->project->slug],
        ], $activity->changes);
    }

    /** @test */
    public function it_removes_project_activities_when_deleted(): void
    {
        $this->assertEquals(1, $this->project->activities()->count());

        $this->project->delete();

        $this->project->forceDelete();

        $this->assertDatabaseMissing('activities', ['project_id' => $this->project->id]);
    }

    /** @test */
    public function record_on_restoring_project(): void
    {
        $this->project->delete();

        $this->patchJson($this->project->path().'/restore')->assertOk();

        $this->project->refresh();

        /** @var \App\Models\Activity $activity */
        $activity = $this->project->activities()->first();

        $this->assertEquals('restored_project', $activity->description);
    }

    /** @test */
    public function record_on_creating_task(): void
    {
        $this->project->addTask('Test Task');

        $this->assertEquals(2, $this->project->activities()->count());

        /** @var \App\Models\Activity $activity */
        $activity = $this->project->activities()->first();

        $this->assertEquals('created_task', $activity->description);

        $this->assertInstanceOf(Task::class, $activity->subject);

        $this->assertEquals('Test Task', $activity->subject->title);
    }

    /** @test */
    public function record_on_updating_task(): void
    {
        $task = $this->project->addTask('test task');

        $this->putJson($task->path(), ['title' => 'changed']);

        /** @var \App\Models\Activity $activity */
        $activity = $this->project->activities()->first();

        $this->assertEquals('updated_task', $activity->description);

        $this->assertEquals([
            'before' => ['title' => $task->title],
            'after' => ['title' => 'changed'],
        ], $activity->changes);
    }

    /** @test */
    public function record_on_task_deletion(): void
    {
        $task = $this->project->addTask('test task');

        $task->delete();

        $this->assertEquals(3, $this->project->activities()->count());

        /** @var \App\Models\Activity $activity */
        $activity = $this->project->activities()->first();

        $this->assertEquals('deleted_task', $activity->description);
    }

    /** @test */
    public function remove_project_task_activities_on_archived_task_deletion(): void
    {
        $task = $this->project->addTask('test task');

        $this->deleteJson(route('task.archive', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]));

        $this->deleteJson($task->path().'/remove');

        /** @var \App\Models\Activity $activity */
        $activity = $this->project->activities()->first();
        $this->assertEquals('deleted_task', $activity->description);
    }

    /** @test */
    public function records_activity_when_invitation_sent_to_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->postJson($this->project->path().'/invitations', [
            'email' => $user->email,
        ]);

        $this->assertEquals(2, $this->project->activities()->count());

        /** @var \App\Models\Activity $activity */
        $activity = $this->project->activities()->first();

        $this->assertEquals('invitation_sent', $activity->description);
    }

    /** @test */
    public function records_activity_when_user_accepted_project_invitation(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->project->invite($user);

        Sanctum::actingAs($user);

        $this->getJson($this->project->path().'/accept-invitation');

        /** @var \App\Models\Activity $activity */
        $activity = $this->project->activities()->first();

        $this->assertEquals('invitation_accepted', $activity->description);
    }

    /** @test */
    public function it_records_activity_when_a_project_member_is_removed(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->project->members()->attach($user, ['active' => true]);

        $this->getJson($this->project->path().'/remove/member/'.$user->uuid);

        /** @var \App\Models\Activity $activity */
        $activity = $this->project->activities()->first();

        $this->assertEquals('member_removed', $activity->description);
    }

    /** @test */
    public function it_records_activity_on_creating_message(): void
    {
        $this->user->forceFill(['is_admin' => true])->save();

        $this->postJson($this->project->path().'/message', [
            'message' => 'this is project message',
            'users' => json_encode([User::first()->id]),
            'subject' => 'this is message subject',
            'sms' => true,
            'mail' => true,
        ]);

        $this->assertEquals(3, $this->project->activities()->count());

        /** @var \App\Models\Activity $activity */
        $activity = $this->project->activities()->first();

        $this->assertEquals('created_message', $activity->description);
    }
}
