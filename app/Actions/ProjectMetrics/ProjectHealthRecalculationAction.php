<?php

declare(strict_types=1);

namespace App\Actions\ProjectMetrics;

use App\Jobs\RecalculateProjectHealth;
use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

final class ProjectHealthRecalculationAction
{
    /**
     * @param  array<int,string>  $sections
     */
    public function execute(Project $project, array $sections): void
    {
        if (! in_array('health', $sections, true)) {
            return;
        }

        $key = "project:{$project->id}:health-persist";
        $decay = (int) config('project-metrics.health.persist_throttle_seconds', 15 * 60);
        $queue = (string) config('project-metrics.health.persist_queue', 'metrics');
        RateLimiter::attempt(
            $key,
            1,
            function () use ($project, $queue, $key): void {
                try {
                    RecalculateProjectHealth::dispatch($project->id, null, true)->onQueue($queue);
                } catch (Throwable $e) {
                    RateLimiter::clear($key);
                    Log::error('ProjectHealthRecalculationAction: failed to dispatch RecalculateProjectHealth', [
                        'project_id' => $project->id,
                        'exception' => $e,
                    ]);
                }
            },
            $decay
        );
    }
}
