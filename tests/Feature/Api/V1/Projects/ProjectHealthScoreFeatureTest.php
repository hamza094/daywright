<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Projects;

use App\Actions\ProjectMetrics\ProjectHealthMetricAction;
use App\Actions\ProjectMetrics\ProjectHealthRecalculationAction;
use App\Events\ProjectHealthUpdated;
use App\Jobs\RecalculateProjectHealth;
use App\Models\Project;
use App\Services\Project\ProjectInsightsPreloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

use function Safe\preg_replace;

class ProjectHealthScoreFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_job_when_health_section_requested_and_sets_broadcast_flag(): void
    {
        // arrange
        Bus::fake();
        $project = Project::factory()->create();
        RateLimiter::clear("project:{$project->id}:health-persist");
        $action = new ProjectHealthRecalculationAction;

        // act
        $action->execute($project, ['health']);

        // assert
        Bus::assertDispatched(RecalculateProjectHealth::class, fn (RecalculateProjectHealth $job): bool => $job->projectId === $project->id && $job->broadcast);
    }

    public function test_does_not_dispatch_when_health_not_requested(): void
    {
        // arrange
        Bus::fake();
        $project = Project::factory()->create();
        $action = new ProjectHealthRecalculationAction;

        // act
        $action->execute($project, ['task-health']);

        // assert
        Bus::assertNotDispatched(RecalculateProjectHealth::class);
    }

    public function test_throttles_duplicate_dispatches_within_decay_window(): void
    {
        // arrange
        Bus::fake();
        RateLimiter::clear('project:1:health-persist');
        $project = Project::factory()->create(['id' => 1]);
        config()->set('project-metrics.health.persist_throttle_seconds', 60);
        $action = new ProjectHealthRecalculationAction;

        // act
        $action->execute($project, ['health']);
        $action->execute($project, ['health']);

        // assert (only first dispatch should occur)
        Bus::assertDispatchedTimes(RecalculateProjectHealth::class, 1);
    }

    public function test_job_persists_health_score_and_timestamp_and_broadcasts_when_flag_true(): void
    {
        // arrange
        Event::fake();
        $project = Project::factory()->create();
        $job = new RecalculateProjectHealth($project->id, 77.5, true);
        $action = app(ProjectHealthMetricAction::class);

        // act
        $job->handle($action);
        $project->refresh();

        // assert
        $this->assertSame(77.5, $project->health_score);
        $this->assertNotNull($project->health_score_calculated_at);
        Event::assertDispatched(ProjectHealthUpdated::class, function (ProjectHealthUpdated $event) use ($project): bool {
            // The broadcasting layer may prefix private channels with "private-".
            // Normalize the channel name before asserting the logical name.
            $channel = $event->broadcastOn();
            $channelName = $channel->name ?? (is_string($channel) ? $channel : '');
            $normalized = preg_replace('/^private-/', '', $channelName);
            $this->assertSame('project.'.$project->id.'.health', $normalized);

            return $event->project->id === $project->id;
        });
    }

    public function test_job_does_not_broadcast_when_flag_false(): void
    {
        // arrange
        Event::fake();
        $project = Project::factory()->create();
        $job = new RecalculateProjectHealth($project->id, 55.0, false);
        $action = app(ProjectHealthMetricAction::class);

        // act
        $job->handle($action);

        // assert
        Event::assertNotDispatched(ProjectHealthUpdated::class);
    }

    public function test_job_computes_score_via_action_when_no_precomputed_score_and_persists_without_broadcast(): void
    {
        // arrange
        Event::fake();
        $project = Project::factory()->create();
        $job = new RecalculateProjectHealth($project->id); // no precomputed score, broadcast defaults to false
        $action = app(ProjectHealthMetricAction::class);
        $preloadedProject = $project->fresh();
        app(ProjectInsightsPreloader::class)->preloadForHealth($preloadedProject);
        $expectedScore = $action->execute($preloadedProject);

        // act
        $job->handle($action);
        $project->refresh();

        // assert
        $this->assertSame($expectedScore, $project->health_score);
        $this->assertNotNull($project->health_score_calculated_at);
        Event::assertNotDispatched(ProjectHealthUpdated::class);
    }

    public function test_project_health_status_accessor_maps_scores_to_labels(): void
    {
        // arrange
        $hot = Project::factory()->create(['health_score' => 85]);
        $warm = Project::factory()->create(['health_score' => 60]);
        $cold = Project::factory()->create(['health_score' => 30]);

        // act + assert
        $this->assertSame('hot', $hot->health_status);
        $this->assertSame('warm', $warm->health_status);
        $this->assertSame('cold', $cold->health_status);
    }

    public function test_backfill_command_dispatches_jobs_for_all_projects(): void
    {
        // arrange
        Bus::fake();
        Project::factory()->count(3)->create();

        // act
        $this->artisan('projects:recalculate-health', ['--limit' => 3])
            ->assertExitCode(0);

        // assert
        Bus::assertDispatched(RecalculateProjectHealth::class, 3);
    }
}
