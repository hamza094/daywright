<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Enums\InsightType;
use Override;

final class HealthInsightBuilder implements InsightBuilderInterface
{
    private const int EXCELLENT_THRESHOLD = 85;

    private const int GOOD_THRESHOLD = 65;

    private const int WARNING_THRESHOLD = 45;

    private const int CRITICAL_THRESHOLD = 20;

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
                'title' => 'No Health Data',
                'message' => 'Insufficient data to calculate project health.',
                'data' => ['value' => null],
            ];
        }

        $score = (float) max(0, min(100, $input));

        return [
            'type' => $this->determineInsightType($score),
            'title' => $this->generateTitle($score),
            'message' => $this->generateMessage($score),
            'data' => ['value' => $score],
        ];
    }

    private function determineInsightType(float $score): string
    {
        return match (true) {
            $score >= self::EXCELLENT_THRESHOLD => InsightType::SUCCESS->value,
            $score >= self::GOOD_THRESHOLD => InsightType::INFO->value,
            $score >= self::WARNING_THRESHOLD => InsightType::WARNING->value,
            default => InsightType::CRITICAL->value,
        };
    }

    private function generateTitle(float $score): string
    {
        return match (true) {
            $score >= self::EXCELLENT_THRESHOLD => 'Excellent Project Health',
            $score >= self::GOOD_THRESHOLD => 'Good Project Health',
            $score >= self::WARNING_THRESHOLD => 'Moderate Project Health',
            default => 'Critical Project Health',
        };
    }

    private function generateMessage(float $score): string
    {
        return match (true) {
            $score >= self::EXCELLENT_THRESHOLD => sprintf(
                'Outstanding project performance at %.1f%%! Strong task completion, healthy communication, active collaboration, and consistent stage progress.',
                $score
            ),
            $score >= self::GOOD_THRESHOLD => sprintf(
                'Good project health at %.1f%%. Solid task delivery with healthy communication and collaboration. Keep the pace to maintain momentum.',
                $score
            ),
            $score >= self::WARNING_THRESHOLD => sprintf(
                'Moderate project health at %.1f%%. Some areas need attention—review task completion, communication, collaboration, and activity levels for improvements.',
                $score
            ),
            $score >= self::CRITICAL_THRESHOLD => sprintf(
                'Project health is concerning at %.1f%%. Multiple areas require immediate attention. Focus on task completion, increase team communication, and boost project activity.',
                $score
            ),
            default => sprintf(
                'Critical project health at %.1f%%. Urgent intervention needed. Review all project metrics, address blocking issues, and implement recovery strategies immediately.',
                $score
            ),
        };
    }
}
