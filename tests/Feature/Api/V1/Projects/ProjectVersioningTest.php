<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Projects;

use App\Enums\ProjectStage;
use App\Models\Project;
use App\Models\Stage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class ProjectVersioningTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function project_api_response_includes_version_field(): void
    {
        $this->getJson(route('api.v1.projects.show', ['project' => $this->project]))
            ->assertOk()
            ->assertJsonPath('data.version', 1);
    }

    /** @test */
    public function project_version_validation_works(): void
    {
        // Missing version fails validation
        $this->patchJson(route('api.v1.projects.update', ['project' => $this->project]), [
            'name' => 'Updated Name',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('version');

        // Invalid version fails validation
        $this->patchJson(route('api.v1.projects.update', ['project' => $this->project]), [
            'name' => 'Updated Name',
            'version' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('version');
    }

    /** @test */
    public function project_version_conflict_detection_works(): void
    {
        // Simulate stale version conflict
        $this->patchJson(route('api.v1.projects.update', ['project' => $this->project]), [
            'name' => 'Updated Name',
            'version' => 999, // Stale version
        ])->assertConflict()
            ->assertJsonPath('code', 'edit_conflict')
            ->assertJsonPath('meta.expected_version', 999)
            ->assertJsonPath('meta.current_version', 1);

        // Verify project was not updated
        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'name' => $this->project->name,
        ]);
    }

    /** @test */
    public function project_version_increments_on_successful_updates(): void
    {
        $this->project->refresh();

        // Initial version should be 1
        $this->assertEquals(1, $this->project->version);

        // Update with correct version
        $response = $this->patchJson(route('api.v1.projects.update', ['project' => $this->project]), [
            'name' => 'Updated Project Name',
            'version' => 1,
        ])->assertOk();

        // Version should increment to 2
        $this->project->refresh();
        $this->assertEquals(2, $this->project->version);
        $response->assertJsonPath('data.version', 2);
    }

    /** @test */
    public function project_stage_update_requires_version(): void
    {
        Stage::factory()->create(['id' => ProjectStage::Design->value]);

        // Missing version fails validation
        $this->patchJson(route('api.v1.projects.stage.update', ['project' => $this->project]), [
            'stage' => 2,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('version');

        // Invalid version fails validation
        $this->patchJson(route('api.v1.projects.stage.update', ['project' => $this->project]), [
            'stage' => 2,
            'version' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('version');
    }

    /** @test */
    public function project_stage_update_version_conflict_detection_works(): void
    {
        Stage::factory()->create(['id' => ProjectStage::Design->value]);

        // Simulate stale version conflict for stage update
        $this->patchJson(route('api.v1.projects.stage.update', ['project' => $this->project]), [
            'stage' => 2,
            'version' => 999, // Stale version
        ])->assertConflict()
            ->assertJsonPath('code', 'edit_conflict')
            ->assertJsonPath('meta.expected_version', 999)
            ->assertJsonPath('meta.current_version', 1);

        // Verify the project version was not updated
        $this->project->refresh();
        $this->assertSame(1, $this->project->version);
    }

    /** @test */
    public function project_stage_update_version_increments_on_successful_updates(): void
    {
        Stage::factory()->create(['id' => ProjectStage::Design->value]);

        $this->project->refresh();

        // Initial version should be 1
        $this->assertEquals(1, $this->project->version);

        // Update stage with correct version
        $response = $this->patchJson(route('api.v1.projects.stage.update', ['project' => $this->project]), [
            'stage' => 2,
            'version' => 1,
        ])->assertOk();

        // Version should increment to 2
        $this->project->refresh();
        $this->assertEquals(2, $this->project->version);
        $response->assertJsonPath('data.version', 2);
    }
}
