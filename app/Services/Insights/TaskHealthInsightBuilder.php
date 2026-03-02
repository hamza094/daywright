<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Enums\InsightType;
use Override;

final class TaskHealthInsightBuilder implements InsightBuilderInterface
{
    private const int EXCELLENT_THRESHOLD = 90;

    private const int GOOD_THRESHOLD = 70;

    private const int WARNING_THRESHOLD = 40;

    private const int HIGH_OVERDUE_THRESHOLD = 25;      // mirrors penalty for overdue in action

    private const int HIGH_ABANDONMENT_THRESHOLD = 15;  // mirrors penalty for abandonment in action

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    #[Override]
    public function build(mixed $input, array $context = []): array
    {
        if ($input === null || ! is_numeric($input)) {
            return [
                'type' => InsightType::INFO->value,
                'title' => 'No Task Data',
                'message' => 'No task data available to generate insights.',
                'data' => ['value' => null],
            ];
        }

        $value = (float) max(0, min(100, $input));
        $summary = $context['summary'] ?? [];

        $type = $this->determineInsightType($value, $summary);

        return [
            'type' => $type,
            'title' => $this->generateTitle($type, $value, $summary),
            'message' => $this->generateMessage($value, $summary),
            'data' => ['value' => $value],
        ];
    }

    /**
     * @param  array<string,mixed>  $summary
     */
    private function determineInsightType(float $value, array $summary): string
    {
        // Prioritize critical issues even with decent scores
        $overdueRate = (float) ($summary['overdue_rate'] ?? 0.0);
        $abandonmentRate = (float) ($summary['abandonment_rate'] ?? 0.0);

        if ($abandonmentRate >= self::HIGH_ABANDONMENT_THRESHOLD || $overdueRate >= self::HIGH_OVERDUE_THRESHOLD) {
            return InsightType::CRITICAL->value;
        }

        return match (true) {
            $value >= self::EXCELLENT_THRESHOLD => InsightType::SUCCESS->value,
            $value >= self::GOOD_THRESHOLD => InsightType::INFO->value,
            $value >= self::WARNING_THRESHOLD => InsightType::WARNING->value,
            default => InsightType::CRITICAL->value,
        };
    }

    /**
     * @param  array<string,mixed>  $summary
     */
    private function generateTitle(string $type, float $value, array $summary): string
    {
        $overdueRate = (float) ($summary['overdue_rate'] ?? 0.0);
        $abandonmentRate = (float) ($summary['abandonment_rate'] ?? 0.0);

        if ($type === InsightType::CRITICAL->value) {
            if ($overdueRate >= self::HIGH_OVERDUE_THRESHOLD) {
                return 'High Overdue Tasks';
            }
            if ($abandonmentRate >= self::HIGH_ABANDONMENT_THRESHOLD) {
                return 'High Task Abandonment';
            }

            return 'Critical Task Issues';
        }

        return match (true) {
            $value >= self::EXCELLENT_THRESHOLD => 'Excellent Task Health',
            $value >= self::GOOD_THRESHOLD => 'Good Task Health',
            $value >= self::WARNING_THRESHOLD => 'Task Health Warning',
            default => 'Critical Task Issues',
        };
    }

    /**
     * @param  array<string,mixed>  $summary
     */
    private function generateMessage(float $value, array $summary): string
    {
        $completionRate = (float) ($summary['completion_rate'] ?? 0.0);
        $overdueRate = (float) ($summary['overdue_rate'] ?? 0.0);
        $abandonmentRate = (float) ($summary['abandonment_rate'] ?? 0.0);
        $active = (int) ($summary['active_count'] ?? 0);
        $completed = (int) ($summary['completed_count'] ?? 0);
        $inProgress = (int) ($summary['in_progress_count'] ?? max(0, $active - $completed));
        $overdue = (int) ($summary['overdue_count'] ?? 0);
        $abandoned = (int) ($summary['abandoned_count'] ?? 0);

        // Build a consistent details line every time
        $details = sprintf(
            'Task health: %.1f%%. Completion: %d%% (%d/%d). Overdue: %d%% of in-progress (%d of %d). Abandoned: %d%% (%d of %d total).',
            $value, (int) $completionRate, $completed, $active, (int) $overdueRate, $overdue, $inProgress, (int) $abandonmentRate, $abandoned, ($active + $abandoned)
        );

        // Optionally prepend a brief issue highlight when thresholds are crossed
        if ($overdueRate >= self::HIGH_OVERDUE_THRESHOLD) {
            return sprintf('High overdue. %s', $details);
        }
        if ($abandonmentRate >= self::HIGH_ABANDONMENT_THRESHOLD) {
            return sprintf('High abandonment. %s', $details);
        }
        if ($completionRate < 45) {
            return sprintf('Low completion. %s', $details);
        }

        return $details;
    }
}
