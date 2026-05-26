<?php

declare(strict_types=1);

namespace App\Actions\ProjectMetrics;

use App\Models\Project;

final class TaskHealthMetricAction
{
    public function execute(Project $project): float
    {
        // Read raw counts from the project (may come from loadCount in repository)
        [$totalTasks, $activeTasks, $completedCount, $overdueCount, $abandonedCount] = $this->getCountsFromProject($project);

        // No tasks -> neutral health
        if ($totalTasks === 0) {
            return 0.0;
        }

        $completionRate = $this->computeCompletionRate($activeTasks, $completedCount);
        $overdueRate = $this->computeOverdueRate($activeTasks, $completedCount, $overdueCount);
        $abandonmentRate = $this->computeAbandonmentRate($totalTasks, $abandonedCount);

        $weights = $this->weights();

        $taskHealth =
            ($completionRate * $weights['completion']) +
            ((100 - $overdueRate) * $weights['overdue']) +
            ((100 - $abandonmentRate) * $weights['abandonment']);

        return max(0, min(100, round($taskHealth, 1)));
    }

    /**
     * Public summary helper that returns task rates and counts.
     * This keeps calculations centralized in the action and prevents duplication.
     */
    /**
     * @return array{
     *   completion_rate: float,
     *   overdue_rate: float,
     *   abandonment_rate: float,
     *   total_count: int,
     *   active_count: int,
     *   completed_count: int,
     *   in_progress_count: int,
     *   overdue_count: int,
     *   abandoned_count: int
     * }
     */
    public function summary(Project $project): array
    {
        [$total, $active, $completed, $overdue, $abandoned] = $this->getCountsFromProject($project);
        $inProgress = max(0, $active - $completed);

        return [
            'completion_rate' => round($this->computeCompletionRate($active, $completed), 1),
            'overdue_rate' => round($this->computeOverdueRate($active, $completed, $overdue), 1),
            'abandonment_rate' => round($this->computeAbandonmentRate($total, $abandoned), 1),
            'total_count' => $total,
            'active_count' => $active,
            'completed_count' => $completed,
            'in_progress_count' => $inProgress,
            'overdue_count' => $overdue,
            'abandoned_count' => $abandoned,
        ];
    }

    /**
     * Extract counts from the project object and normalize to ints.
     * Returns: [total, active, completed, overdue, abandoned]
     *
     * @return list<int> [total, active, completed, overdue, abandoned]
     */
    private function getCountsFromProject(Project $project): array
    {
        $total = (int) ($project->tasks_count ?? 0);
        $active = (int) ($project->active_tasks_count ?? 0);
        $completed = (int) ($project->completed_tasks_count ?? 0);
        $overdue = (int) ($project->overdue_tasks_count ?? 0);
        $abandoned = (int) ($project->abandoned_tasks_count ?? 0);

        return [$total, $active, $completed, $overdue, $abandoned];
    }

    /**
     * Compute completion rate as percentage of completed tasks over active (non-abandoned) tasks.
     */
    private function computeCompletionRate(int $activeTasks, int $completedCount): float
    {
        if ($activeTasks <= 0) {
            return 0.0;
        }

        return ($completedCount / $activeTasks) * 100;
    }

    /**
     * Compute overdue rate as percentage, capped at 100%
     */
    private function computeOverdueRate(int $activeTasks, int $completedCount, int $overdueCount): float
    {
        $nonCompletedActive = max(0, $activeTasks - $completedCount);
        if ($nonCompletedActive <= 0) {
            return 0.0;
        }

        return min(100.0, ($overdueCount / $nonCompletedActive) * 100);
    }

    /**
     * Compute abandonment rate as percentage
     */
    private function computeAbandonmentRate(int $totalTasks, int $abandonedCount): float
    {
        if ($totalTasks <= 0) {
            return 0.0;
        }

        return ($abandonedCount / $totalTasks) * 100;
    }

    /**
     * Return normalized weight fractions for task metric components.
     *
     * @return array{completion: float, overdue: float, abandonment: float}
     */
    private function weights(): array
    {
        $cfg = (array) config('project-metrics.health.tasks.weights', []);
        $completion = (float) ($cfg['completion'] ?? 50);
        $overdue = (float) ($cfg['overdue'] ?? 30);
        $abandonment = (float) ($cfg['abandonment'] ?? 20);
        $sum = $completion + $overdue + $abandonment;

        if ($sum <= 0.0) {
            return [
                'completion' => 0.5,
                'overdue' => 0.3,
                'abandonment' => 0.2,
            ];
        }

        return [
            'completion' => $completion / $sum,
            'overdue' => $overdue / $sum,
            'abandonment' => $abandonment / $sum,
        ];
    }
}
