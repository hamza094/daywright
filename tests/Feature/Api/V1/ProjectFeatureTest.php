<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Jobs\CancelZoomMeetingsJob;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use App\Traits\ProjectSetup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProjectFeatureTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    private const PROJECTS_ROUTE = 'projects.store';

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

        $project = Project::where('slug', '=', $response->json('project.slug'))->firstOrFail();

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
        $response = $this->postJson('/api/v1/projects', [
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

        $response = $this->postJson('api/v1/projects', $attributes);

        $response->assertJsonValidationErrors('tasks');
    }

    /** @test */
    public function auth_user_can_get_project_resource(): void
    {
        $response = $this->getJson($this->project->path())
            ->assertOk();

        $response->assertJson([
            'id' => $this->project->id,
            'name' => $this->project->name,
        ]);
    }

    /** @test */
    public function allowed_user_can_update_project(): void
    {
        $name = 'My First Project';
        $notes = 'My project first notes';

        $response = $this->patchJson($this->project->path(),
            ['name' => $name, 'notes' => $notes]);

        $this->assertDatabaseHas('projects', ['id' => $this->project->id,
            'name' => $name]);

        $this->project->refresh();

        $response
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Project Updated Successfully',
                'project' => [
                    'name' => $this->project->name,
                    'slug' => $this->project->slug,
                ],
            ]);
    }

    /** @test */
    public function updated_project_requires_a_name(): void
    {
        $response = $this->patchJson($this->project->path(),
            ['name' => null])->assertUnprocessable();

        $response->assertJsonMissingValidationErrors('project.name');
    }

    /** @test */
    public function it_does_not_update_with_invalid_fields(): void
    {
        $response = $this->patchJson($this->project->path(),
            ['invalid_field' => 'Some value'])
            ->assertStatus(400);

        $response->assertJson([
            'error' => "You haven't changed anything.",
        ]);
    }

    /** @test */
    public function it_does_not_update_field_with_same_data(): void
    {
        $project = Project::factory()->create(['name' => 'Xepra Tech']);

        $response = $this->patchJson($project->path(),
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

        $this->deleteJson($this->project->path());

        $this->assertCount(0, $this->user->projects()->get());

        $this->assertSoftDeleted($this->project);
    }

    /** @test */
    public function trashed_project_activity_request_returns_project_not_active_message(): void
    {
        $this->project->delete();

        $this->getJson("/api/v1/projects/{$this->project->slug}/activities")
            ->assertForbidden()
            ->assertJson([
                'message' => 'Sorry, project is not active. Restore it to perform this activity.',
            ]);
    }

    /** @test */
    public function project_owner_can_restore_project(): void
    {
        $this->project->touch('deleted_at');

        $this->patchJson($this->project->path().'/restore')->assertOk();

        $this->project->refresh();

        $this->assertNotSoftDeleted($this->project);

        $this->assertEquals($this->project->deleted_at, null);
    }

    /** @test */
    public function active_project_cannot_be_deleted_permanently(): void
    {
        $response = $this->deleteJson($this->project->path().'/force');

        $response->assertForbidden()->assertJson([
            'message' => 'Only abandoned projects can be deleted permanently.',
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
        ]);
    }

    /** @test */
    public function abandoned_project_can_be_deleted_permanently(): void
    {
        $this->project->delete();

        $this->deleteJson($this->project->path().'/force')->assertOk();

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

        $this->deleteJson($this->project->path().'/force')->assertOk();

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

        $this->deleteJson($this->project->path().'/force')->assertOk();

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
}
