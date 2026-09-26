<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Tasks;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class TaskVersioningTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function task_api_response_includes_version_field(): void
    {
        $task = $this->project->addTask('Test Task');

        $this->getJson(route('api.v1.tasks.show', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.version', 1);
    }

    /** @test */
    public function task_version_validation_works(): void
    {
        $task = $this->project->addTask('Test Task');

        // Missing version fails validation
        $this->patchJson(route('api.v1.tasks.update', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]), [
            'title' => 'Updated Title',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('version');

        // Invalid version fails validation
        $this->patchJson(route('api.v1.tasks.update', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]), [
            'title' => 'Updated Title',
            'version' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('version');
    }

    /** @test */
    public function task_version_conflict_detection_works(): void
    {
        $task = $this->project->addTask('Test Task');

        // Simulate stale version conflict
        $this->patchJson(route('api.v1.tasks.update', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]), [
            'title' => 'Updated Title',
            'version' => 999, // Stale version
        ])->assertConflict()
            ->assertJsonPath('code', 'edit_conflict')
            ->assertJsonPath('meta.expected_version', 999)
            ->assertJsonPath('meta.current_version', 1);

        // Verify task was not updated
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Test Task',
        ]);
    }

    /** @test */
    public function task_version_increments_on_successful_updates(): void
    {
        $task = $this->project->addTask('Test Task');
        $task->refresh();

        // Initial version should be 1
        $this->assertEquals(1, $task->version);

        // Update with correct version
        $response = $this->patchJson(route('api.v1.tasks.update', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]), [
            'title' => 'Updated Title',
            'version' => 1,
        ])->assertOk();

        // Version should increment to 2
        $task->refresh();
        $this->assertEquals(2, $task->version);
        $response->assertJsonPath('data.version', 2);
    }

    /** @test */
    public function setting_task_status_to_current_state_succeeds_without_side_effects(): void
    {
        $task = $this->project->addTask('Test Task');
        $task->refresh();
        $currentStatus = $task->status_id;
        $originalVersion = $task->version;

        // Set status to current state (no-op transition)
        $response = $this->patchJson(route('api.v1.tasks.update', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]), [
            'status_id' => $currentStatus,
            'version' => $originalVersion,
        ])->assertOk();

        // Version should NOT increment for no-op transitions
        $task->refresh();
        $this->assertEquals($originalVersion, $task->version);
        $this->assertEquals($currentStatus, $task->status_id);

        // Status should remain the same
        $response->assertJsonPath('data.status.id', $currentStatus);
        $response->assertJsonPath('data.version', $originalVersion);
    }

    /** @test */
    public function setting_task_status_to_current_state_with_other_field_changes_increments_version(): void
    {
        $task = $this->project->addTask('Test Task');
        $task->refresh();
        $currentStatus = $task->status_id;
        $originalVersion = $task->version;

        // Set status to current state but change another field
        $response = $this->patchJson(route('api.v1.tasks.update', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]), [
            'status_id' => $currentStatus,
            'title' => 'Updated Title',
            'version' => $originalVersion,
        ])->assertOk();

        // Version should increment because title changed
        $task->refresh();
        $this->assertEquals($originalVersion + 1, $task->version);
        $this->assertEquals($currentStatus, $task->status_id);
        $this->assertEquals('Updated Title', $task->title);

        // Response should reflect the changes
        $response->assertJsonPath('data.status.id', $currentStatus);
        $response->assertJsonPath('data.version', $originalVersion + 1);
        $response->assertJsonPath('data.title', 'Updated Title');
    }
}
