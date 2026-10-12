<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Project;

use App\DataTransferObjects\Project\ProjectStageUpdateData;
use App\Enums\ProjectStage;
use App\Models\Meeting;
use App\Models\Stage;
use App\Services\Project\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

final class ProjectServiceTest extends TestCase
{
    use ProjectSetup;
    use RefreshDatabase;

    #[Test]
    public function it_uses_the_project_resource_load_profile_for_project_details(): void
    {
        Meeting::factory()->for($this->project)->for($this->user)->create();

        $project = app(ProjectService::class)->loadForDetails($this->project->fresh());

        $this->assertTrue($project->relationLoaded('user'));
        $this->assertTrue($project->relationLoaded('stage'));
        $this->assertTrue($project->relationLoaded('activeMembers'));
        $this->assertTrue($project->relationLoaded('limitedActivities'));
        $this->assertFalse($project->relationLoaded('meetings'));

        $activity = $project->limitedActivities->first();

        $this->assertNotNull($activity);
        $this->assertTrue($activity->relationLoaded('user'));
        $this->assertTrue($activity->relationLoaded('subject'));
    }

    #[Test]
    public function it_updates_postponed_reason_when_stage_is_postponed_and_unchanged(): void
    {
        $postponedStage = Stage::factory()->create(['id' => ProjectStage::Postponed->value]);
        $this->project->update(['stage_id' => $postponedStage->id, 'postponed_reason' => null, 'version' => 1]);
        $this->project->refresh();

        $initialVersion = $this->project->version;

        $data = new ProjectStageUpdateData(
            stageId: $this->project->stage_id,
            version: $initialVersion,
            postponedReason: 'Budget constraints',
        );

        $updatedProject = app(ProjectService::class)->updateStageStatus($this->project, $data, $this->user);

        $this->assertSame('Budget constraints', $updatedProject->postponed_reason);
        $this->assertSame($initialVersion + 1, $updatedProject->version);
    }

    #[Test]
    public function it_clears_postponed_reason_when_stage_changes_from_postponed(): void
    {
        $postponedStage = Stage::factory()->create(['id' => ProjectStage::Postponed->value]);
        $newStage = Stage::factory()->create(['id' => ProjectStage::Development->value]);
        $this->project->update(['stage_id' => $postponedStage->id, 'postponed_reason' => 'Old reason', 'version' => 1]);
        $this->project->refresh();

        $initialVersion = $this->project->version;

        $data = new ProjectStageUpdateData(
            stageId: $newStage->id,
            version: $initialVersion,
            postponedReason: 'Should be ignored',
        );

        $updatedProject = app(ProjectService::class)->updateStageStatus($this->project, $data, $this->user);

        $this->assertNull($updatedProject->postponed_reason);
        $this->assertSame($initialVersion + 1, $updatedProject->version);
    }

    #[Test]
    public function it_returns_noop_when_both_stage_and_reason_are_unchanged(): void
    {
        $postponedStage = Stage::factory()->create(['id' => ProjectStage::Postponed->value]);
        $this->project->update(['stage_id' => $postponedStage->id, 'postponed_reason' => 'Initial reason', 'version' => 1]);
        $this->project->refresh();

        $initialVersion = $this->project->version;

        $data = new ProjectStageUpdateData(
            stageId: $postponedStage->id,
            version: $initialVersion,
            postponedReason: 'Initial reason',
        );

        $updatedProject = app(ProjectService::class)->updateStageStatus($this->project, $data, $this->user);

        $this->assertSame('Initial reason', $updatedProject->postponed_reason);
        $this->assertSame($initialVersion, $updatedProject->version);
    }
}
