<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\ProjectMetrics\ProjectHealthMetricAction;
use App\Events\ProjectHealthUpdated;
use App\Models\Project;
use App\Services\Project\ProjectInsightsPreloader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecalculateProjectHealth implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of attempts and timeout per attempt.
     */
    public int $tries = 2;

    public int $timeout = 60; // seconds

    public function __construct(public int $projectId, public ?float $precomputedScore = null, public bool $broadcast = false) {}

    /**
     * Exponential-like backoff between retries.
     *
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return [40, 60];
    }

    public function handle(ProjectHealthMetricAction $action, ?ProjectInsightsPreloader $preloader = null): void
    {
        $project = $this->findProject();
        if (! $project instanceof Project) {
            return;
        }

        $this->maybePreload($preloader, $project);

        $score = $this->determineScore($project, $action);

        $this->persistScore($project, $score);

        $this->maybeBroadcast($project);
    }

    public function failed(Throwable $e): void
    {
        Log::error('RecalculateProjectHealth failed', [
            'project_id' => $this->projectId,
            'message' => $e->getMessage(),
        ]);
    }

    /**
     * Locate the project model by id.
     */
    private function findProject(): ?Project
    {
        return Project::query()->find($this->projectId);
    }

    /**
     * Resolve the preloader if necessary and preload related counts unless a
     * precomputed score was already supplied. Ensures transient attributes are
     * not persisted.
     */
    private function maybePreload(?ProjectInsightsPreloader $preloader, Project $project): void
    {
        if (is_float($this->precomputedScore)) {
            return;
        }

        $preloader ??= app(ProjectInsightsPreloader::class);

        $preloader->preloadForHealth($project);

        $project->syncOriginal();
    }

    /**
     * Determine score via supplied action or use a provided precomputed value.
     */
    private function determineScore(Project $project, ProjectHealthMetricAction $action): float
    {
        if (is_float($this->precomputedScore)) {
            return $this->precomputedScore; // Trust upstream provided value
        }

        return $action->execute($project);
    }

    /**
     * Persist the new score and timestamp quietly.
     */
    private function persistScore(Project $project, float $score): void
    {
        $project->forceFill([
            'health_score' => $score,
            'health_score_calculated_at' => now(),
        ])->saveQuietly();
    }

    /**
     * Broadcast update event if requested.
     */
    private function maybeBroadcast(Project $project): void
    {
        if (! $this->broadcast) {
            return;
        }

        event(new ProjectHealthUpdated($project));
    }
}
