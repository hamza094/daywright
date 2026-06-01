<?php

declare(strict_types=1);

namespace App\Actions\ProjectMetrics;

use App\Models\Project;

final class ActivityHealthMetricAction
{
    public function execute(Project $project): float
    {
        $count = max(0, (int) ($project->recent_activities_count ?? 0));

        $full = (int) config('project-metrics.progress.activity_count_for_full', 15);
        if ($full <= 0) {
            return 0.0;
        }

        // Diminishing returns via log curve, consistent with other metrics
        $base = (float) config('project-metrics.progress.activity_log_base', 2.0);
        $base = $base > 1 ? $base : 2.0;

        $num = log(1 + $count, $base);
        $den = log(1 + $full, $base);
        if ($den <= 0) {
            return 0.0;
        }

        $fraction = $num / $den; // ~1.0 around the configured "full" activity count
        $percent = $this->clampPercent($fraction * 100.0);

        return round($percent, 1);
    }

    private function clampPercent(float $value): float
    {
        if (! is_finite($value) || $value < 0.0) {
            return 0.0;
        }

        if ($value > 100.0) {
            return 100.0;
        }

        return $value;
    }
}
