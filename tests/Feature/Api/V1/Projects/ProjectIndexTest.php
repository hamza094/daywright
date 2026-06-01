<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Projects;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class ProjectIndexTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function it_validates_sort_parameter(): void
    {
        $response = $this->getJson(route('api.v1.projects.index', ['sort' => 'invalid_sort']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sort'])
            ->assertJson([
                'message' => 'Validation failed.',
                'code' => 'validation_error',
                'errors' => [
                    'sort' => ['Sort must be one of: -created_at, created_at, name, or -name'],
                ],
            ]);
    }

    /** @test */
    public function it_validates_member_parameter(): void
    {
        $response = $this->getJson(route('api.v1.projects.index', ['filter' => ['member' => 'not_a_boolean']]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filter.member'])
            ->assertJson([
                'message' => 'Validation failed.',
                'code' => 'validation_error',
                'errors' => [
                    'filter.member' => ['The filter.member field must be true or false.'],
                ],
            ]);
    }

    /** @test */
    public function it_accepts_string_true_member_parameter(): void
    {
        $response = $this->getJson(route('api.v1.projects.index', ['filter' => ['member' => 'true']]));

        $response->assertOk()
            ->assertJsonMissingValidationErrors(['filter.member']);
    }

    /** @test */
    public function it_validates_page_parameter(): void
    {
        $response = $this->getJson(route('api.v1.projects.index', ['page' => 0]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page'])
            ->assertJson([
                'message' => 'Validation failed.',
                'code' => 'validation_error',
                'errors' => [
                    'page' => ['Page must be at least 1'],
                ],
            ]);
    }

    /** @test */
    public function it_validates_per_page_parameter_bounds(): void
    {
        $this->getJson(route('api.v1.projects.index', ['per_page' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page'])
            ->assertJson([
                'message' => 'Validation failed.',
                'code' => 'validation_error',
                'errors' => [
                    'per_page' => ['Per page must be at least 1'],
                ],
            ]);

        $this->getJson(route('api.v1.projects.index', ['per_page' => 101]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page'])
            ->assertJson([
                'message' => 'Validation failed.',
                'code' => 'validation_error',
                'errors' => [
                    'per_page' => ['Per page may not be greater than 100'],
                ],
            ]);
    }

    /** @test */
    public function it_accepts_valid_parameters(): void
    {
        Project::factory()->create(['name' => 'Test Project', 'user_id' => $this->user->id]);

        $response = $this->getJson(route('api.v1.projects.index', [
            'sort' => '-created_at',
            'filter' => [
                'member' => true,
                'abandoned' => false,
                'search' => 'Test',
            ],
            'page' => 1,
            'per_page' => 10,
        ]));

        $response->assertOk()
            ->assertJsonMissingValidationErrors(['sort', 'filter.member', 'filter.abandoned', 'page', 'filter.search', 'per_page']);
    }

    /** @test */
    public function auth_user_can_filter_projects_by_search(): void
    {
        // Create projects with different names
        $frontendProject = Project::factory()->create(['name' => 'Frontend Project', 'user_id' => $this->user->id]);
        Project::factory()->create(['name' => 'Backend Project', 'user_id' => $this->user->id]);
        Project::factory()->create(['name' => 'Mobile App', 'user_id' => $this->user->id]);

        $response = $this->getJson(route('api.v1.projects.index', ['filter' => ['search' => 'Frontend']]));

        $response->assertOk();

        $projects = $response->json('data');

        // The search should only return "Frontend Project"
        $this->assertCount(1, $projects);
        $this->assertEquals('Frontend Project', $projects[0]['name']);
        $this->assertEquals($this->apiV1Route('projects.show', ['project' => $frontendProject]), $projects[0]['links']['self']);
        $this->assertEquals($frontendProject->created_at?->setTimezone('UTC')->toIso8601String(), $projects[0]['created_at']);
    }

    /** @test */
    public function auth_user_search_treats_sql_wildcards_as_literals(): void
    {
        $literalMatch = Project::factory()->create([
            'name' => 'Client% Portal',
            'user_id' => $this->user->id,
        ]);
        Project::factory()->create([
            'name' => 'ClientX Portal',
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson(route('api.v1.projects.index', ['filter' => ['search' => 'Client%']]))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($literalMatch->id, $response->json('data.0.id'));
    }

    /** @test */
    public function auth_user_rejects_legacy_top_level_filter_aliases(): void
    {
        Project::factory()->create(['name' => 'Frontend Project', 'user_id' => $this->user->id]);
        Project::factory()->create(['name' => 'Backend Project', 'user_id' => $this->user->id]);

        $this->getJson(route('api.v1.projects.index', [
            'search' => 'Frontend',
            'member' => true,
            'abandoned' => false,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['search', 'member', 'abandoned']);
    }

    /** @test */
    public function auth_user_rejects_unsupported_nested_filter_keys(): void
    {
        $this->getJson(route('api.v1.projects.index', [
            'filter' => ['status' => 'healthy'],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filter']);
    }

    /** @test */
    public function auth_user_rejects_unsupported_top_level_query_parameters(): void
    {
        $this->getJson(route('api.v1.projects.index', [
            'include' => 'members',
            'fields' => ['projects' => 'id,name'],
            'append' => 'metrics',
            'random' => 'value',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['include', 'fields', 'append', 'random']);
    }

    /** @test */
    public function auth_user_can_sort_projects_by_latest(): void
    {
        Project::factory()->create([
            'name' => 'Old Project',
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(5),
        ]);

        $latestProject = $this->project; // Assuming this is the default project created in ProjectSetup

        $response = $this->getJson(route('api.v1.projects.index', ['sort' => '-created_at']));
        $projects = $response->json('data');
        $this->assertEquals($latestProject->name, $projects[0]['name']);
    }

    /** @test */
    public function auth_user_projects_default_to_newest_first_when_sort_is_omitted(): void
    {
        Project::factory()->create([
            'name' => 'Old Project',
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->getJson(route('api.v1.projects.index'))
            ->assertOk();

        $this->assertSame($this->project->id, $response->json('data.0.id'));
    }

    /** @test */
    public function auth_user_can_sort_projects_by_oldest(): void
    {
        Project::factory()->create([
            'name' => 'Old Project',
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(5),
        ]);
        // Assuming this is the default project created in ProjectSetup

        $response = $this->getJson(route('api.v1.projects.index', ['sort' => 'created_at']));
        $projects = $response->json('data');
        $this->assertEquals('Old Project', $projects[0]['name']);
    }

    /** @test */
    public function auth_user_can_sort_projects_by_name(): void
    {
        Project::factory()->create([
            'name' => 'Zoo Project',
            'user_id' => $this->user->id,
        ]);

        Project::factory()->create([
            'name' => 'Alpha Project',
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson(route('api.v1.projects.index', ['sort' => 'name']));

        $response->assertOk();

        $projects = $response->json('data');
        $projectNames = collect($projects)->pluck('name')->all();

        $this->assertContains('Alpha Project', $projectNames);
        $this->assertContains('Zoo Project', $projectNames);
        $this->assertLessThan(
            array_search('Zoo Project', $projectNames, true),
            array_search('Alpha Project', $projectNames, true),
        );
    }

    /** @test */
    public function legacy_sort_aliases_are_rejected(): void
    {
        Project::factory()->create([
            'name' => 'Old Project',
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(5),
        ]);

        $latestResponse = $this->getJson(route('api.v1.projects.index', ['sort' => 'latest']));
        $latestResponse->assertStatus(422)->assertJsonValidationErrors(['sort']);

        $oldestResponse = $this->getJson(route('api.v1.projects.index', ['sort' => 'oldest']));
        $oldestResponse->assertStatus(422)->assertJsonValidationErrors(['sort']);
    }

    /** @test */
    public function auth_user_can_view_member_projects(): void
    {
        // Create a project owned by another user
        $otherUser = User::factory()->create();
        $memberProject = Project::factory()->create(['user_id' => $otherUser->id]);

        // Add current user as member
        DB::table('project_members')->insert([
            'project_id' => $memberProject->id,
            'user_id' => $this->user->id,
            'active' => 1,
        ]);

        $response = $this->getJson(route('api.v1.projects.index', ['filter' => ['member' => true]]));

        $response->assertOk();

        $projects = $response->json('data');

        $this->assertCount(1, $projects);
        $this->assertEquals($memberProject->name, $projects[0]['name']);
    }

    /** @test */
    public function auth_user_can_view_trashed_projects(): void
    {
        // Soft delete the default project
        $this->project->delete();

        $response = $this->getJson(route('api.v1.projects.index', ['filter' => ['abandoned' => true]]));

        $response->assertOk();

        $projects = $response->json('data');

        $this->assertCount(1, $projects);
        $this->assertEquals($this->project->name, $projects[0]['name']);
    }

    /** @test */
    public function auth_user_can_limit_projects_per_page(): void
    {
        Project::factory()->count(5)->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.v1.projects.index', ['per_page' => 2]))
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }
}
