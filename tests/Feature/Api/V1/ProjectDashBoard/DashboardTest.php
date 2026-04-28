<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class DashboardTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function auth_user_can_view_dashboard_projects(): void
    {
        // Create 5 projects for the user
        Project::factory()->count(5)->for($this->user)->create();

        $response = $this->getJson('/api/v1/dashboard/projects');

        $response->assertOk()
            ->assertJsonStructure([
                'projects',
                'projectsCount',
                'message',
            ]);

        // Should only return 3 projects (latest)
        $this->assertCount(3, $response->json('projects'));
        $this->assertEquals(3, $response->json('projectsCount'));
        $this->assertNotEmpty($response->json('projects'));
    }

    /** @test */
    public function dashboard_projects_returns_empty_message_when_no_projects(): void
    {
        // Delete the default project from ProjectSetup trait
        $this->project->delete();

        $response = $this->getJson('/api/v1/dashboard/projects');

        $response->assertOk();

        $this->assertCount(0, $response->json('projects'));
        $this->assertEquals(0, $response->json('projectsCount'));
        $this->assertEquals('No active projects found', $response->json('message'));
    }
}
