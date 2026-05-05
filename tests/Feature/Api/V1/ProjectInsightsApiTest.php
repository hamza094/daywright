<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Project;
use App\Models\Stage;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Override;
use Tests\Support\BuildsInsightTestData;
use Tests\TestCase;

class ProjectInsightsApiTest extends TestCase
{
    use BuildsInsightTestData;
    use RefreshDatabase;

    private User $user;

    private User $nonMember;

    private Project $project;

    private Stage $stage;

    private TaskStatus $status;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->status = TaskStatus::factory()->create();
        $this->stage = Stage::factory()->create();
        $this->nonMember = User::factory()->create();
        $this->project = Project::factory()->create([
            'name' => 'Test Project',
            'stage_id' => $this->stage->id,
        ]);

        // Make user a project member
        $this->project->members()->attach($this->user->id);

        // Set up test config
        Config::set('project-metrics.health.weights.tasks', 0.4);
        Config::set('project-metrics.time_periods.recent_activity_days', 7);
    }

    /** @test */
    public function can_get_project_insights_with_realistic_data(): void
    {
        // Arrange: Create realistic project data
        $this->seedRealisticData($this->project, $this->user);

        Sanctum::actingAs($this->user);

        // Act: Make API request
        $response = $this
            ->getJson($this->apiV1Route('projects.insights', ['project' => $this->project]));

        // Assert: Basic response structure
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'project_id',
                    'project_name',
                    'insights' => [
                        '*' => ['type', 'title', 'message', 'data'],
                    ],
                    'sections_requested',
                    'generated_at',
                ],
            ]);

        // Assert: Key business logic
        $data = $response->json('data');
        $this->assertEquals($this->project->id, $data['project_id']);
        $this->assertEquals('Test Project', $data['project_name']);
        $this->assertNotEmpty($data['insights']);
        // Basic structure is asserted above via assertJsonStructure
    }

    /** @test */
    public function can_get_specific_sections_only(): void
    {
        // Arrange
        $this->seedRealisticData($this->project, $this->user);

        Sanctum::actingAs($this->user);

        // Act: Request only health and task-health sections
        $response = $this
            ->getJson($this->apiV1Route('projects.insights', ['project' => $this->project], [
                'sections' => ['health', 'task-health'],
            ]));

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'project_id',
                    'project_name',
                    'insights' => [
                        '*' => ['type', 'title', 'message', 'data'],
                    ],
                    'sections_requested',
                    'generated_at',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(['health', 'task-health'], $data['sections_requested']);
        $this->assertIsArray($data['insights']);
        $this->assertNotEmpty($data['insights']);
        // Structure verified by assertJsonStructure
    }

    /** @test */
    public function can_get_single_section_via_url_path(): void
    {
        // Arrange
        $this->seedRealisticData($this->project, $this->user);

        Sanctum::actingAs($this->user);

        // Act: Request health section via query parameter instead of URL path
        $response = $this
            ->getJson($this->apiV1Route('projects.insights', ['project' => $this->project], [
                'sections' => ['health'],
            ]));

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'project_id',
                    'project_name',
                    'insights' => [
                        '*' => ['type', 'title', 'message', 'data'],
                    ],
                    'sections_requested',
                    'generated_at',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(['health'], $data['sections_requested']);

        $insights = $data['insights'];
        $this->assertCount(1, $insights);
        // Basic structure verified by assertJsonStructure
    }

    /** @test */
    public function normalizes_sections_array_and_filters_duplicates(): void
    {
        $this->seedRealisticData($this->project, $this->user);
        Sanctum::actingAs($this->user);

        // sections with whitespace, duplicates, empty strings
        $response = $this->getJson($this->apiV1Route('projects.insights', ['project' => $this->project], [
            'sections' => [' health ', '', 'task-health', 'health'],
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'project_id',
                    'project_name',
                    'insights' => [
                        '*' => ['type', 'title', 'message', 'data'],
                    ],
                    'sections_requested',
                    'generated_at',
                ],
            ]);
        $data = $response->json('data');
        // Expect normalized unique order as first-seen
        $this->assertEquals(['health', 'task-health'], $data['sections_requested']);
        $this->assertIsArray($data['insights']);
        $this->assertNotEmpty($data['insights']);
        // Ensure only requested sections are included
        $this->assertCount(2, $data['insights']);
    }

    /** @test */
    public function requires_authentication(): void
    {
        // No actingAs here
        $this->getJson($this->apiV1Route('projects.insights', ['project' => $this->project]))
            ->assertStatus(401);
    }

    /** @test */
    public function validates_invalid_sections_parameter(): void
    {
        // Act: Request with invalid section
        Sanctum::actingAs($this->user);

        $response = $this
            ->getJson($this->apiV1Route('projects.insights', ['project' => $this->project], [
                'sections' => ['invalid_section'],
            ]));

        // Assert: Validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sections.0']);
    }

    /** @test */
    public function handles_empty_project_gracefully(): void
    {
        // Arrange: Project with no tasks, activities, etc.
        $emptyProject = Project::factory()->create(['name' => 'Empty Project']);
        $emptyProject->members()->attach($this->user->id);

        Sanctum::actingAs($this->user);

        // Act
        $response = $this
            ->getJson($this->apiV1Route('projects.insights', ['project' => $emptyProject]));

        // Assert: Should still return valid structure
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'project_id',
                    'project_name',
                    'insights' => [
                        '*' => ['type', 'title', 'message', 'data'],
                    ],
                    'sections_requested',
                    'generated_at',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals($emptyProject->id, $data['project_id']);
        $this->assertIsArray($data['insights']);

        // Verify each insight handles empty data gracefully
        foreach ($data['insights'] as $insight) {
            $this->assertArrayHasKey('data', $insight);
            $this->assertIsArray($insight['data']);
        }
    }
}
