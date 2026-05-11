<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Project;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createAdminUser();
        $this->enableTwoFactorForUser($this->admin);

        Sanctum::actingAs($this->admin);
    }

    // Authorization

    #[Test]
    public function non_admin_cannot_access_projects_index(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->getJson($this->apiV1AdminRoute('projects.index'))
            ->assertForbidden();
    }

    #[Test]
    public function non_admin_cannot_bulk_delete_projects(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        /** @var Project $project */
        $project = $this->createProject();

        $this->deleteJson($this->apiV1AdminRoute('projects.bulk-delete'), ['project_ids' => [$project->id]])
            ->assertForbidden();
    }

    // Index & Filters

    #[Test]
    public function admin_can_list_projects(): void
    {
        Project::factory()->count(3)->create();

        $this->getJson($this->apiV1AdminRoute('projects.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['applied_filters'],
                'links',
            ]);
    }

    #[Test]
    public function returns_empty_message_when_no_projects(): void
    {
        $this->getJson($this->apiV1AdminRoute('projects.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.applied_filters', []);
    }

    #[Test]
    public function can_filter_projects_by_search(): void
    {
        $alphaProject = $this->createProject(['name' => 'Alpha Project']);
        $this->createProject(['name' => 'Beta Project']);

        $response = $this->getJson($this->projectsUrl(['search' => 'Alpha']))
            ->assertOk();

        $projects = $response->json('data');
        $this->assertIsArray($projects);
        $this->assertCount(1, $projects);
        $this->assertStringContainsString('Alpha', $projects[0]['name']);
        $this->assertEquals($this->apiV1Route('projects.show', ['project' => $alphaProject]), $projects[0]['links']['self']);
    }

    #[Test]
    public function can_filter_projects_by_active_and_trashed(): void
    {
        $this->createProject();
        $trashed = $this->createProject();
        $trashed->delete();

        // Active filter
        $active = $this->getJson($this->projectsUrl(['state' => 'active']))->assertOk();
        $this->assertNotEmpty($active->json('data'));
        $this->assertContains('Filter by Active', $active->json('meta.applied_filters'));

        // Trashed filter
        $trashedResponse = $this->getJson($this->projectsUrl(['state' => 'trashed']))->assertOk();
        $this->assertNotEmpty($trashedResponse->json('data'));
        $this->assertContains('Filter by Trashed', $trashedResponse->json('meta.applied_filters'));
    }

    #[Test]
    public function can_filter_projects_by_health_status(): void
    {
        $this->createProject(['health_score' => 80]);
        $this->createProject(['health_score' => 20]);

        $response = $this->getJson($this->projectsUrl(['status' => 'hot']))
            ->assertOk();

        $projects = $response->json('data');
        $this->assertIsArray($projects);
        $this->assertCount(1, $projects);
    }

    #[Test]
    public function validates_search_max_length(): void
    {
        $this->getJson($this->projectsUrl(['search' => str_repeat('a', 256)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter.search');
    }

    #[Test]
    public function validates_invalid_status_filter(): void
    {
        $this->getJson($this->projectsUrl(['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter.status');
    }

    #[Test]
    public function can_filter_projects_by_stage(): void
    {
        /** @var Stage $stage */
        $stage = Stage::factory()->create();
        $this->createProject(['stage_id' => $stage->id]);
        $this->createProject();

        $response = $this->getJson($this->projectsUrl(['stage' => $stage->id]))
            ->assertOk();

        $appliedFilters = $response->json('meta.applied_filters');
        $this->assertNotEmpty($appliedFilters);
    }

    #[Test]
    public function search_filter_does_not_leak_across_other_filters(): void
    {
        /** @var User $matchingUser */
        $matchingUser = $this->createUser(['name' => 'SearchableUser']);
        /** @var Project $activeProject */
        $activeProject = $this->createProject([
            'name' => 'Unrelated',
            'user_id' => $matchingUser->id,
        ]);

        $trashedProject = $this->createProject([
            'name' => 'TrashedSearchable',
            'user_id' => $matchingUser->id,
        ]);
        $trashedProject->delete();

        // Search by user name + active filter: should NOT return the trashed project
        $response = $this->getJson($this->projectsUrl([
            'search' => 'SearchableUser',
            'state' => 'active',
        ]))
            ->assertOk();

        $projects = $response->json('data');
        $this->assertIsArray($projects);

        $projectIds = collect($projects)->pluck('id')->toArray();
        $this->assertContains($activeProject->id, $projectIds);
        $this->assertNotContains($trashedProject->id, $projectIds);
    }

    #[Test]
    public function paginates_projects(): void
    {
        Project::factory()->count(15)->create();

        $response = $this->getJson($this->projectsUrl(params: ['per_page' => 5]))
            ->assertOk();

        $projects = $response->json('data');
        $this->assertIsArray($projects);
        $this->assertCount(5, $projects);
    }

    // Bulk Delete

    #[Test]
    public function admin_can_bulk_delete_projects(): void
    {
        /** @var \Illuminate\Support\Collection<int, Project> $projects */
        $projects = Project::factory()->count(3)->create();
        $projects->each(fn (Project $project): bool => $project->delete());
        $ids = $projects->pluck('id')->toArray();

        $this->deleteJson($this->apiV1AdminRoute('projects.bulk-delete'), ['project_ids' => $ids])
            ->assertOk()
            ->assertJsonPath('message', 'Projects deleted successfully.');

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('projects', ['id' => $id]);
        }
    }

    #[Test]
    public function bulk_delete_can_delete_trashed_projects(): void
    {
        /** @var Project $project */
        $project = $this->createProject();
        $project->delete();

        $this->deleteJson($this->apiV1AdminRoute('projects.bulk-delete'), ['project_ids' => [$project->id]])
            ->assertOk();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    #[Test]
    public function bulk_delete_does_not_force_delete_active_projects(): void
    {
        /** @var Project $activeProject */
        $activeProject = $this->createProject();

        $this->deleteJson($this->apiV1AdminRoute('projects.bulk-delete'), ['project_ids' => [$activeProject->id]])
            ->assertOk()
            ->assertJsonPath('message', 'Projects deleted successfully.');

        $this->assertDatabaseHas('projects', ['id' => $activeProject->id]);
    }

    #[Test]
    public function bulk_delete_validates_project_ids(): void
    {
        $this->deleteJson($this->apiV1AdminRoute('projects.bulk-delete'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_ids');

        $this->deleteJson($this->apiV1AdminRoute('projects.bulk-delete'), ['project_ids' => [99999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_ids.0');

        /** @var Project $project */
        $project = $this->createProject();

        $this->deleteJson($this->apiV1AdminRoute('projects.bulk-delete'), [
            'project_ids' => [$project->id, $project->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_ids.0');
    }

    private function enableTwoFactorForUser(User $user): void
    {
        $user->createTwoFactorAuth();

        $user->enableTwoFactorAuth();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $params
     */
    private function projectsUrl(array $filters = [], array $params = []): string
    {
        $query = $params;

        if ($filters !== []) {
            $query['filter'] = $filters;
        }

        return $this->apiV1AdminRoute('projects.index', query: $query);
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
    private function createAdminUser(array $attributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->admin()->create($attributes);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createProject(array $attributes = []): Project
    {
        /** @var Project $project */
        $project = Project::factory()->create($attributes);

        return $project;
    }
}
