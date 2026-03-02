<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\ProjectMetrics\ActivityHealthMetricAction;
use App\Actions\ProjectMetrics\CommunicationHealthMetricAction;
use App\Actions\ProjectMetrics\ProjectHealthMetricAction;
use App\Actions\ProjectMetrics\StageProgressMetricAction;
use App\Actions\ProjectMetrics\TaskHealthMetricAction;
use App\Actions\ProjectMetrics\TeamCollaborationMetricAction;
use App\Actions\ProjectMetrics\UpcomingRiskMetricAction;
use App\Enums\ProjectStage;
use App\Models\Project;
use Mockery;
use Override;
use Tests\TestCase;

class ProjectInsightsCalculationsTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function calculate_project_health_combines_all_health_metrics(): void
    {
        // Arrange
        $taskHealth = Mockery::mock(TaskHealthMetricAction::class);
        $collaborationHealth = Mockery::mock(TeamCollaborationMetricAction::class);
        $stageProgress = Mockery::mock(StageProgressMetricAction::class);
        $communicationHealth = Mockery::mock(CommunicationHealthMetricAction::class);
        $activityHealth = Mockery::mock(ActivityHealthMetricAction::class);

        // Use a real Project instance for attribute assignments to avoid Mockery setAttribute errors
        $project = new Project;

        // Mock config
        config(['project-metrics.health.weights' => [
            'tasks' => 0.3,
            'communication' => 0.2,
            'collaboration' => 0.2,
            'stage' => 0.2,
            'activity' => 0.1,
        ]]);

        // Mock method calls
        $taskHealth->shouldReceive('execute')->with($project)->once()->andReturn(80.0);
        $collaborationHealth->shouldReceive('execute')->with($project)->once()->andReturn(60.0);
        $stageProgress->shouldReceive('execute')->with($project)->once()->andReturn([
            'percentage' => 75,
            'current_stage' => 'Development',
            'status' => 'active',
            'stage_id' => 3,
        ]);

        // Communication and Activity health are delegated; mock their results
        $communicationHealth->shouldReceive('execute')->with($project)->once()->andReturn(70.0);
        $activityHealth->shouldReceive('execute')->with($project)->once()->andReturn(33.3);

        $action = new ProjectHealthMetricAction(
            $taskHealth,
            $collaborationHealth,
            $stageProgress,
            $communicationHealth,
            $activityHealth
        );

        // Act
        $result = $action->execute($project);

        // Assert - Expected: (80*0.3) + (70*0.2) + (60*0.2) + (75*0.2) + (33.3*0.1) = 24 + 14 + 12 + 15 + 3.33 = 68.3
        $this->assertEquals(68.3, $result);
        $this->assertIsFloat($result);
    }

    /** @test */
    public function calculate_task_health_weighted_by_completion_overdue_and_abandonment(): void
    {
        // Arrange
        $project = new Project;
        $project->tasks_count = 10;
        $project->active_tasks_count = 10; // active denominator used by new logic
        $project->completed_tasks_count = 6;
        $project->overdue_tasks_count = 3;

        $action = new TaskHealthMetricAction;

        // Act
        $result = $action->execute($project);

        // Assert
        // completionRate = 6/10 = 60
        // overdueRate = overdue / (active - completed) = 3/(10-6) = 75; contributes as (100 - 75) = 25
        // abandonmentRate = abandoned/total = 0/10 = 0; contributes as (100 - 0) = 100
        // taskHealth = 60*0.5 + 25*0.3 + 100*0.2 = 30 + 7.5 + 20 = 57.5
        $this->assertEquals(57.5, $result);
    }

    /** @test */
    public function calculate_task_health_handles_empty_project(): void
    {
        // Arrange
        $project = new Project;

        $project->tasks_count = 0;

        $action = new TaskHealthMetricAction;

        // Act
        $result = $action->execute($project);

        // Assert
        $this->assertEquals(0.0, $result);
    }

    /** @test */
    public function calculate_collaboration_health_uses_members_meetings_and_participation(): void
    {
        // Arrange (use defaults from action/config):
        // - ideal_team_size = 8 → memberRate = (members/8)*100
        // - ideal_meetings = 3 with log base 2 → meetingRate = log2(1+meetings)/log2(1+3)*100
        // - participationRate = participants/members * 100
        // - weights (members/meetings/participation) = 40% / 30% / 30%
        $project = new Project;
        $project->active_members_count = 4;      // member rate = 4/8 = 50%
        $project->recent_meetings_count = 2;     // meeting rate ≈ log2(3)/log2(4) ≈ 79.25%
        $project->recent_participants_count = 3; // participation = 3/4 = 75%

        $action = new TeamCollaborationMetricAction;

        // Act
        $result = $action->execute($project);

        // Assert - Expected weighted total = 50*0.4 + 79.25*0.3 + 75*0.3 = 66.275 → 66.3
        $this->assertEquals(66.3, $result);
    }

    /** @test */
    public function stage_progress_returns_stage_data_and_preserves_enum_stage_id(): void
    {
        // Arrange
        $project = new Project;
        $project->stage_id = ProjectStage::Development->value;
        $action = new StageProgressMetricAction;

        // Act
        $result = $action->execute($project);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('percentage', $result);
        $this->assertArrayHasKey('current_stage', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals(ProjectStage::Development->value, $result['stage_id']);
    }

    /** @test */
    public function stage_progress_returns_correct_status_for_completed_and_postponed(): void
    {
        $project = new Project;
        $project->stage_id = ProjectStage::Completed->value;

        $action = new StageProgressMetricAction;
        $completedResult = $action->execute($project);
        $this->assertEquals('completed', $completedResult['status']);

        $project->stage_id = ProjectStage::Postponed->value;
        $postponedResult = $action->execute($project);
        $this->assertEquals('postponed', $postponedResult['status']);
    }

    /** @test */
    public function upcoming_risk_returns_score_and_counts(): void
    {
        // Arrange: UpcomingRiskMetricAction now reads precomputed counts from the project
        $project = new Project;
        $project->tasks_due_soon_count = 2;
        $project->tasks_at_risk_count = 1;

        $action = new UpcomingRiskMetricAction;

        // Act
        $result = $action->execute($project);

        // Assert - shape: score, at_risk_count, due_soon_count
        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('at_risk_count', $result);
        $this->assertArrayHasKey('due_soon_count', $result);
        $this->assertEquals(2, $result['due_soon_count']);
        $this->assertEquals(1, $result['at_risk_count']);
        // atRisk/dueSoon = 1/2 => 50.0, severity boost for 1 => 1.0
        $this->assertEquals(50.0, $result['score']);
    }

    /** @test */
    public function communication_health_calculates_log_scaled_score_and_caps(): void
    {
        $project = new Project;
        $action = new CommunicationHealthMetricAction;

        // 7 conversations with base 2 and scale 15 → log2(1+7)=3 → 3*15=45 (max_score 100)
        config([
            'project-metrics.health.communication.max_score' => 100,
            'project-metrics.health.communication.scale' => 15,
            'project-metrics.health.communication.log_base' => 2.0,
        ]);
        $project->recent_conversations_count = 7;
        $this->assertEquals(45.0, $action->execute($project));

    }

    /** @test */
    public function activity_health_uses_log_fraction_and_clamps(): void
    {
        $project = new Project;
        $action = new ActivityHealthMetricAction;

        config([
            'project-metrics.progress.activity_count_for_full' => 75,
            'project-metrics.progress.activity_log_base' => 2.0,
        ]);

        // 0 activity → 0%
        $project->recent_activities_count = 0;
        $this->assertEquals(0.0, $action->execute($project));

        // 3 activities: log2(4)/log2(76) ≈ 2/6.26 ≈ 0.32 → 32.0%
        $project->recent_activities_count = 3;
        $this->assertEquals(32.0, $action->execute($project));

        // 15 activities: log2(16)/log2(76) ≈ 4/6.26 ≈ 0.64 → 64.0%
        $project->recent_activities_count = 15;
        $this->assertEquals(64.0, $action->execute($project));
    }
}
