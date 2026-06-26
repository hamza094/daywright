<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Projects;

use App\Enums\TaskStatus as TaskStatusEnum;
use App\Jobs\CancelZoomMeetingsJob;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class ProjectFeatureTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    private const string PROJECTS_ROUTE = 'api.v1.projects.store';

    /** @test */
    public function auth_user_can_create_project(): void
    {
        $this->postJson(route(self::PROJECTS_ROUTE),
            [
                'name' => 'My Project name',
                'about' => 'about this project',
                'stage_id' => 1,
            ]);

        $this->assertDatabaseHas('projects', ['name' => 'My Project name']);
    }

    /** @test */
    public function tasks_can_be_added_when_new_project_created(): void
    {
        $attributes = Project::factory()->raw([
            'user_id' => auth()->id(),
        ]);

        $attributes['tasks'] = [
            ['title' => 'task 1'],
            ['title' => 'task 2'],
        ];

        $response = $this->postJson(route(self::PROJECTS_ROUTE), $attributes);

        $project = Project::where('slug', '=', $response->json('data.slug'))->firstOrFail();

        $this->assertCount(2, $project->tasks);
    }

    /** @test */
    public function project_requires_a_name(): void
    {
        $project = Project::factory()->make(['name' => null]);

        $response = $this->postJson(route(self::PROJECTS_ROUTE), $project->toArray());

        $response->assertJsonValidationErrors('name');
    }

    /** @test */
    public function tasks_validated_on_creating_a_new_project(): void
    {
        $response = $this->postJson($this->apiV1Route('projects.store'), [
            'name' => 'project name',
            'about' => 'about this project',
            'stage_id' => 1,
            'tasks' => [
                ['title' => str_repeat('a', 56)],
                ['title' => ''],
            ],
        ]);

        $response->assertJsonValidationErrors('tasks.0.title');
        $response->assertJsonValidationErrors('tasks.1.title');
    }

    /** @test */
    public function project_cannot_have_more_than_three_tasks(): void
    {
        $attributes = Project::factory()->raw([
            'user_id' => auth()->id(),
        ]);

        $attributes['tasks'] = [
            ['title' => 'Task 1'],
            ['title' => 'Task 2'],
            ['title' => 'Task 3'],
            ['title' => 'Task 4'], // exceeds the limit
        ];

        $response = $this->postJson($this->apiV1Route('projects.store'), $attributes);

        $response->assertJsonValidationErrors('tasks');
    }

    /** @test */
    public function auth_user_can_get_project_resource(): void
    {
        $response = $this->getJson($this->apiV1Route('projects.show', ['project' => $this->project]))
            ->assertOk();

        $response->assertJsonPath('data.id', $this->project->id);
        $response->assertJsonPath('data.name', $this->project->name);
        $response->assertJsonPath('data.links.self', $this->apiV1Route('projects.show', ['project' => $this->project]));
        $response->assertJsonPath('data.created_at', $this->project->created_at?->setTimezone('UTC')->toIso8601String());
    }

    /** @test */
    public function project_show_serializes_owner_members_and_recent_activity_users_with_summary_resources(): void
    {
        /** @var User $member */
        $member = User::factory()->create();

        $this->project->members()->attach($member, ['active' => true]);
        $this->project->addTask('Ship API cleanup');

        $response = $this->getJson($this->apiV1Route('projects.show', ['project' => $this->project]))
            ->assertOk();

        $response
            ->assertJsonPath('data.user.id', $this->user->id)
            ->assertJsonPath('data.user.uuid', $this->user->uuid)
            ->assertJsonPath('data.user.username', $this->user->username)
            ->assertJsonMissingPath('data.user.email')
            ->assertJsonPath('data.members.0.id', $member->id)
            ->assertJsonPath('data.members.0.uuid', $member->uuid)
            ->assertJsonPath('data.members.0.username', $member->username)
            ->assertJsonMissingPath('data.members.0.email')
            ->assertJsonPath('data.activities.0.user.id', $this->user->id)
            ->assertJsonPath('data.activities.0.user.uuid', $this->user->uuid)
            ->assertJsonPath('data.activities.0.user.username', $this->user->username)
            ->assertJsonMissingPath('data.activities.0.user.email');
    }

    /** @test */
    public function project_show_includes_project_scoped_limits_only(): void
    {
        Task::factory()->count(2)->for($this->user, 'owner')->for($this->project)->create([
            'status_id' => TaskStatusEnum::PENDING,
        ]);
        Task::factory()->for($this->user, 'owner')->for($this->project)->completed()->create();

        $this->project->members()->attach(User::factory()->count(2)->create(), ['active' => true]);
        $this->project->members()->attach(User::factory()->create(), ['active' => false]);

        $response = $this->getJson($this->apiV1Route('projects.show', ['project' => $this->project]))
            ->assertOk()
            ->assertJsonCount(2, 'data.limits');

        $this->assertProjectLimitItem($response, 'data.limits', 'tasks_per_project', 'Tasks', 'project', 3, 10);
        $this->assertProjectLimitItem($response, 'data.limits', 'members_per_project', 'Members', 'project', 2, 3);
    }

    /** @test */
    public function project_member_cannot_see_project_limits_on_show(): void
    {
        /** @var User $member */
        $member = User::factory()->create();

        Task::factory()->count(2)->for($this->user, 'owner')->for($this->project)->create([
            'status_id' => TaskStatusEnum::PENDING,
        ]);
        $this->project->members()->attach($member, ['active' => true]);

        Sanctum::actingAs($member);

        $this->getJson($this->apiV1Route('projects.show', ['project' => $this->project]))
            ->assertOk()
            ->assertJsonMissingPath('data.limits');
    }

    /** @test */
    public function allowed_user_can_update_project(): void
    {
        $name = 'My First Project';
        $notes = 'My project first notes';

        Task::factory()->count(2)->for($this->user, 'owner')->for($this->project)->create([
            'status_id' => TaskStatusEnum::PENDING,
        ]);
        $this->project->members()->attach(User::factory()->count(2)->create(), ['active' => true]);

        $response = $this->patchJson($this->apiV1Route('projects.update', ['project' => $this->project]),
            ['name' => $name, 'notes' => $notes]);

        $this->assertDatabaseHas('projects', ['id' => $this->project->id,
            'name' => $name]);

        $this->project->refresh();

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.name', $this->project->name)
            ->assertJsonPath('data.slug', $this->project->slug)
            ->assertJsonPath('data.links.self', $this->apiV1Route('projects.show', ['project' => $this->project]))
            ->assertJsonCount(2, 'data.limits');

        $this->assertProjectLimitItem($response, 'data.limits', 'tasks_per_project', 'Tasks', 'project', 2, 10);
        $this->assertProjectLimitItem($response, 'data.limits', 'members_per_project', 'Members', 'project', 2, 3);
    }

    /** @test */
    public function project_member_cannot_see_project_limits_on_update(): void
    {
        /** @var User $member */
        $member = User::factory()->create();
        $this->project->members()->attach($member, ['active' => true]);

        Sanctum::actingAs($member);

        $this->patchJson($this->apiV1Route('projects.update', ['project' => $this->project]), [
            'name' => 'Updated By Member',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated By Member')
            ->assertJsonMissingPath('data.limits');
    }

    /** @test */
    public function updated_project_requires_a_name(): void
    {
        $response = $this->patchJson($this->apiV1Route('projects.update', ['project' => $this->project]),
            ['name' => null])->assertUnprocessable();

        $response->assertJsonMissingValidationErrors('project.name');
    }

    /** @test */
    public function it_does_not_update_with_invalid_fields(): void
    {
        $response = $this->patchJson($this->apiV1Route('projects.update', ['project' => $this->project]),
            ['invalid_field' => 'Some value'])
            ->assertStatus(400);

        $response->assertJsonPath('message', "You haven't changed anything.");
    }

    /** @test */
    public function it_does_not_update_field_with_same_data(): void
    {
        $project = Project::factory()->create(['name' => 'Xepra Tech']);

        $response = $this->patchJson($this->apiV1Route('projects.update', ['project' => $project]),
            [
                'name' => $project->name,
            ])->assertStatus(422);

        $response->assertJsonValidationErrors([
            'name' => 'The name must be different from the current name.',
        ]);
    }

    /** @test */
    public function project_owner_can_get_abandoned_project(): void
    {
        $this->assertCount(1, $this->user->projects()->get());

        $this->deleteJson($this->apiV1Route('projects.destroy', ['project' => $this->project]));

        $this->assertCount(0, $this->user->projects()->get());

        $this->assertSoftDeleted($this->project);
    }

    /** @test */
    public function trashed_project_activity_request_returns_project_not_active_message(): void
    {
        $this->project->delete();

        $this->getJson($this->apiV1Route('projects.activities', ['project' => $this->project]))
            ->assertConflict()
            ->assertJsonPath('message', 'Project is archived. Restore it before performing this action.')
            ->assertJsonPath('code', 'project_archived');
    }

    /** @test */
    public function project_owner_can_restore_project(): void
    {
        $this->project->touch('deleted_at');

        $this->patchJson($this->apiV1Route('projects.restore', ['project' => $this->project]))->assertOk();

        $this->project->refresh();

        $this->assertNotSoftDeleted($this->project);

        $this->assertEquals($this->project->deleted_at, null);
    }

    /** @test */
    public function active_project_cannot_be_deleted_permanently(): void
    {
        $response = $this->deleteJson($this->apiV1Route('projects.force-delete', ['project' => $this->project]));

        $response->assertForbidden()
            ->assertJsonPath('message', 'Only abandoned projects can be deleted permanently.')
            ->assertJsonPath('code', 'forbidden');

        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
        ]);
    }

    /** @test */
    public function abandoned_project_can_be_deleted_permanently(): void
    {
        $this->project->delete();

        $this->deleteJson($this->apiV1Route('projects.force-delete', ['project' => $this->project]))
            ->assertOk()
            ->assertJsonPath('message', 'Project deleted successfully.');

        $this->assertModelMissing($this->project);
    }

    /** @test */
    public function force_deleting_abandoned_project_dispatches_zoom_cancellation_job_with_meeting_primitives(): void
    {
        Queue::fake([CancelZoomMeetingsJob::class]);

        $anotherUser = User::factory()->create();

        $meetingOne = Meeting::factory()
            ->for($this->project)
            ->for($this->user)
            ->create();

        $meetingTwo = Meeting::factory()
            ->for($this->project)
            ->for($anotherUser)
            ->create();

        $this->project->delete();

        $this->deleteJson($this->apiV1Route('projects.force-delete', ['project' => $this->project]))->assertOk();

        Queue::assertPushed(
            CancelZoomMeetingsJob::class,
            function (CancelZoomMeetingsJob $job) use ($meetingOne, $meetingTwo, $anotherUser): bool {
                $payload = collect($job->meetings);

                return $payload->count() === 2
                    && $payload->contains(fn (array $meeting): bool => $meeting['meeting_id'] === (int) $meetingOne->meeting_id
                        && $meeting['user_id'] === (int) $this->user->id)
                    && $payload->contains(fn (array $meeting): bool => $meeting['meeting_id'] === (int) $meetingTwo->meeting_id
                        && $meeting['user_id'] === (int) $anotherUser->id);
            }
        );
    }

    /** @test */
    public function force_deleting_abandoned_project_without_meetings_does_not_dispatch_zoom_cancellation_job(): void
    {
        Queue::fake([CancelZoomMeetingsJob::class]);

        $this->project->delete();

        $this->deleteJson($this->apiV1Route('projects.force-delete', ['project' => $this->project]))->assertOk();

        Queue::assertNotPushed(CancelZoomMeetingsJob::class);
    }

    public function delete_abandon_projects_after_limit_past(): void
    {
        $this->project->touch('deleted_at');

        $this->assertCount(1, $this->user->projects()
            ->onlyTrashed()->get());

        Project::factory()
            ->for($this->user)
            ->create(['deleted_at' => Carbon::now()->subDays(91)]);

        $this->assertCount(2, $this->user->projects()
            ->onlyTrashed()->get());

        $this->artisan('remove:abandon')->assertSuccessful();

        $this->assertCount(1, $this->user->projects()
            ->onlyTrashed()->get());
    }

    /**
     * @param  \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    private function assertProjectLimitItem(
        $response,
        string $path,
        string $key,
        string $expectedLabel,
        string $expectedScope,
        int $expectedUsed,
        int $expectedMax,
    ): void {
        /** @var array<int, array{key: string, label: string, scope: string, limit: array{used: int|null, max: int|null}}>|null $limits */
        $limits = $response->json($path);

        $item = collect($limits)->firstWhere('key', $key);

        $this->assertIsArray($item);
        $this->assertSame($expectedLabel, $item['label']);
        $this->assertSame($expectedScope, $item['scope']);
        $this->assertSame($expectedUsed, $item['limit']['used']);
        $this->assertSame($expectedMax, $item['limit']['max']);
    }
}
