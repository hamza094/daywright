<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\ProjectDashboard;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class UserProjectsPageTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function it_validates_sort_parameter(): void
    {
        $response = $this->getJson(route('api.v1.projects.index', ['sort' => 'invalid_sort']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sort'])
            ->assertJson([
                'message' => 'Validation Error',
                'errors' => [
                    'sort' => ['Sort must be one of: latest, oldest, or name'],
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
                'message' => 'Validation Error',
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
                'message' => 'Validation Error',
                'errors' => [
                    'page' => ['Page must be at least 1'],
                ],
            ]);
    }

    /** @test */
    public function it_accepts_valid_parameters(): void
    {
        Project::factory()->create(['name' => 'Test Project', 'user_id' => $this->user->id]);

        $response = $this->getJson(route('api.v1.projects.index', [
            'sort' => 'latest',
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
        $this->assertEquals($frontendProject->path(), $projects[0]['links']['self']);
        $this->assertEquals($frontendProject->created_at?->setTimezone('UTC')->toIso8601String(), $projects[0]['created_at']);
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

        $response = $this->getJson(route('api.v1.projects.index', ['sort' => 'latest']));
        $projects = $response->json('data');
        $this->assertEquals($latestProject->name, $projects[0]['name']);
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

        $response = $this->getJson(route('api.v1.projects.index', ['sort' => 'oldest']));
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
