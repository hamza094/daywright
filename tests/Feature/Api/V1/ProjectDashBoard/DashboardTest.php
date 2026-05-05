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

        $response = $this->getJson($this->apiV1Route('dashboard.projects'));

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['total'],
            ]);

        // Should only return 3 projects (latest)
        $this->assertCount(3, $response->json('data'));
        $this->assertEquals(3, $response->json('meta.total'));
        $this->assertNotEmpty($response->json('data'));

        collect($response->json('data'))->pluck('links.self')->each(function (?string $path): void {
            $this->assertNotNull($path);
            $this->assertStringStartsWith($this->apiV1Route('projects.index').'/', $path);
        });
    }

    /** @test */
    public function dashboard_projects_returns_empty_message_when_no_projects(): void
    {
        // Delete the default project from ProjectSetup trait
        $this->project->delete();

        $response = $this->getJson($this->apiV1Route('dashboard.projects'));

        $response->assertOk();

        $this->assertCount(0, $response->json('data'));
        $this->assertEquals(0, $response->json('meta.total'));
    }
}
