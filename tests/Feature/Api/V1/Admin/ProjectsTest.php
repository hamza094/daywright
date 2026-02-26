<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Project;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectsTest extends TestCase
{
    use RefreshDatabase;

    private const PROJECTS_ROUTE = '/api/v1/admin/projects';

    private const BULK_DELETE_ROUTE = '/api/v1/admin/projects/bulk-delete';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->enableTwoFactorForUser($this->admin);

        Sanctum::actingAs($this->admin);
    }

    // Authorization

    #[Test]
    public function non_admin_cannot_access_projects_index(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson(self::PROJECTS_ROUTE)
            ->assertForbidden();
    }

    #[Test]
    public function non_admin_cannot_bulk_delete_projects(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $project = Project::factory()->create();

        $this->deleteJson(self::BULK_DELETE_ROUTE, ['project_ids' => [$project->id]])
            ->assertForbidden();
    }

    // Index & Filters

    #[Test]
    public function admin_can_list_projects(): void
    {
        Project::factory()->count(3)->create();

        $this->getJson(self::PROJECTS_ROUTE)
            ->assertOk()
            ->assertJsonStructure(['projects', 'appliedFilters']);
    }

    #[Test]
    public function returns_empty_message_when_no_projects(): void
    {
        $this->getJson(self::PROJECTS_ROUTE)
            ->assertOk()
            ->assertJsonPath('message', 'Sorry no result found');
    }

    #[Test]
    public function can_filter_projects_by_search(): void
    {
        Project::factory()->create(['name' => 'Alpha Project']);
        Project::factory()->create(['name' => 'Beta Project']);

        $response = $this->getJson(self::PROJECTS_ROUTE.'?search=Alpha')
            ->assertOk();

        $projects = $response->json('projects');
        $this->assertCount(1, $projects);
        $this->assertStringContainsString('Alpha', $projects[0]['name']);
    }

    #[Test]
    public function can_filter_projects_by_active_and_trashed(): void
    {
        Project::factory()->create();
        $trashed = Project::factory()->create();
        $trashed->delete();

        // Active filter
        $active = $this->getJson(self::PROJECTS_ROUTE.'?filter=active')->assertOk();
        $this->assertNotEmpty($active->json('projects'));
        $this->assertContains('Filter by Active', $active->json('appliedFilters'));

        // Trashed filter
        $trashedResponse = $this->getJson(self::PROJECTS_ROUTE.'?filter=trashed')->assertOk();
        $this->assertNotEmpty($trashedResponse->json('projects'));
        $this->assertContains('Filter by Trashed', $trashedResponse->json('appliedFilters'));
    }

    #[Test]
    public function can_filter_projects_by_health_status(): void
    {
        Project::factory()->create(['health_score' => 80]);
        Project::factory()->create(['health_score' => 20]);

        $response = $this->getJson(self::PROJECTS_ROUTE.'?status=hot')
            ->assertOk();

        $projects = $response->json('projects');
        $this->assertCount(1, $projects);
    }

    #[Test]
    public function validates_invalid_status_filter(): void
    {
        $this->getJson(self::PROJECTS_ROUTE.'?status=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    #[Test]
    public function can_filter_projects_by_stage(): void
    {
        $stage = Stage::factory()->create();
        Project::factory()->create(['stage_id' => $stage->id]);
        Project::factory()->create();

        $response = $this->getJson(self::PROJECTS_ROUTE."?stage={$stage->id}")
            ->assertOk();

        $appliedFilters = $response->json('appliedFilters');
        $this->assertNotEmpty($appliedFilters);
    }

    #[Test]
    public function paginates_projects(): void
    {
        Project::factory()->count(15)->create();

        $response = $this->getJson(self::PROJECTS_ROUTE)
            ->assertOk();

        $projects = $response->json('projects');
        $this->assertCount(10, $projects);
    }

    // Bulk Delete

    #[Test]
    public function admin_can_bulk_delete_projects(): void
    {
        $projects = Project::factory()->count(3)->create();
        $ids = $projects->pluck('id')->toArray();

        $this->deleteJson(self::BULK_DELETE_ROUTE, ['project_ids' => $ids])
            ->assertOk()
            ->assertJsonPath('message', 'Projects deleted Successfully');

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('projects', ['id' => $id]);
        }
    }

    #[Test]
    public function bulk_delete_can_delete_trashed_projects(): void
    {
        $project = Project::factory()->create();
        $project->delete();

        $this->deleteJson(self::BULK_DELETE_ROUTE, ['project_ids' => [$project->id]])
            ->assertOk();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    #[Test]
    public function bulk_delete_validates_project_ids(): void
    {
        $this->deleteJson(self::BULK_DELETE_ROUTE, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_ids');

        $this->deleteJson(self::BULK_DELETE_ROUTE, ['project_ids' => [99999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_ids.0');

        $project = Project::factory()->create();

        $this->deleteJson(self::BULK_DELETE_ROUTE, [
            'project_ids' => [$project->id, $project->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_ids.0');
    }

    private function enableTwoFactorForUser(User $user): void
    {
        $twoFactor = $user->createTwoFactorAuth();

        $twoFactor->forceFill([
            'label' => "DayWright:{$user->email}",
        ])->save();

        $user->enableTwoFactorAuth();
    }
}
