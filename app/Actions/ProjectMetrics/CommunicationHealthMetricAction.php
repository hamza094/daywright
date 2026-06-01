<?php

declare(strict_types=1);

namespace App\Actions\ProjectMetrics;

use App\Models\Project;

final class CommunicationHealthMetricAction
{
    /**
     * Calculate communication health from recent conversations using a
     * logarithmic growth curve with config-driven scaling and cap.
     */
    public function execute(Project $project): float
    {
        $conversationCount = $this->getRecentConversationCount($project);
        $config = $this->resolveCommunicationConfig();
        $rawScore = $this->computeRawScore($conversationCount, $config['logBase'], $config['scale']);

        return $this->clampScore($rawScore, $config['maxScore']);
    }

    /**
     * Count recent conversations (never negative).
     */
    private function getRecentConversationCount(Project $project): int
    {
        return max(0, (int) ($project->recent_conversations_count ?? 0));
    }

    /**
     * Read and sanitize communication scoring configuration.
     * - maxScore: cap for the score
     * - scale: multiplier for the log growth
     * - logBase: logarithm base (> 1 to be valid)
     *
     * Returns an associative array with keys: maxScore, scale, logBase.
     *
     * @return array{maxScore: float, scale: float, logBase: float}
     */
    private function resolveCommunicationConfig(): array
    {
        $maxScore = (float) config('project-metrics.health.communication.max_score');
        $scale = (float) config('project-metrics.health.communication.scale');
        $logBase = (float) config('project-metrics.health.communication.log_base');

        // Sane guards if config keys are missing or invalid
        $logBase = $logBase > 1 ? $logBase : 2.0; // base must be > 1 for log
        $scale = is_finite($scale) ? $scale : 0.0;
        $maxScore = is_finite($maxScore) ? $maxScore : 0.0;

        return [
            'maxScore' => $maxScore,
            'scale' => $scale,
            'logBase' => $logBase,
        ];
    }

    /**
     * Compute score with diminishing returns: log(1 + count, base) * scale
     */
    private function computeRawScore(int $conversationCount, float $logBase, float $scale): float
    {
        return log(1 + $conversationCount, $logBase) * $scale;
    }

    /**
     * Clamp score to a maximum cap.
     */
    private function clampScore(float $score, float $maxScore): float
    {
        return min($maxScore, $score);
    }
}
