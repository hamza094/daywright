<?php

declare(strict_types=1);

namespace App\Actions\ProjectMetrics;

use App\Models\Project;

final class UpcomingRiskMetricAction
{
    /**
     * @return array{score: float, at_risk_count: int, due_soon_count: int}
     */
    public function execute(Project $project): array
    {
        // Use pre-loaded counts from repository for consistency and performance
        $dueSoon = max(0, (int) ($project->tasks_due_soon_count ?? 0));
        $atRisk = max(0, (int) ($project->tasks_at_risk_count ?? 0));

        return [
            'score' => $this->calculateRiskScore($dueSoon, $atRisk),
            'at_risk_count' => $atRisk,
            'due_soon_count' => $dueSoon,
        ];
    }

    /**
     * Calculate risk score (0-100, higher = more risky)
     */
    private function calculateRiskScore(int $dueSoon, int $atRisk): float
    {
        if ($dueSoon === 0) {
            return 0.0;
        }

        $riskPercentage = ($atRisk / $dueSoon) * 100;
        $severityBoost = $this->getSeverityBoost($atRisk);

        return round(min(100, $riskPercentage * $severityBoost), 1);
    }

    /**
     * Get severity boost multiplier based on at-risk task count
     */
    private function getSeverityBoost(int $atRiskCount): float
    {
        return match (true) {
            $atRiskCount >= 10 => 1.5,
            $atRiskCount >= 5 => 1.2,
            $atRiskCount >= 1 => 1.0,
            default => 0.0,
        };
    }
}
