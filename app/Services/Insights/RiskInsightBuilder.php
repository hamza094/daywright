<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Enums\InsightType;
use Override;

final class RiskInsightBuilder implements InsightBuilderInterface
{
    private const int HIGH_RISK_THRESHOLD = 70;

    private const int MODERATE_RISK_THRESHOLD = 40;

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    #[Override]
    public function build(mixed $input, array $context = []): array
    {
        if (! is_array($input) || ! isset($input['score'])) {
            return [
                'type' => InsightType::INFO->value,
                'title' => 'No Risk Data',
                'message' => 'No risk data available.',
                'data' => ['value' => null],
            ];
        }

        $score = (float) max(0, min(100, $input['score']));
        $atRiskCount = (int) ($input['at_risk_count'] ?? 0);
        $dueSoonCount = (int) ($input['due_soon_count'] ?? 0);

        return [
            'type' => $this->determineInsightType($score, $atRiskCount),
            'title' => $this->generateTitle($score),
            'message' => $this->generateMessage($score, $atRiskCount, $dueSoonCount),
            'data' => ['value' => $score],
        ];
    }

    private function determineInsightType(float $score, int $atRiskCount): string
    {
        return match (true) {
            $score >= self::HIGH_RISK_THRESHOLD => InsightType::CRITICAL->value,
            $score >= self::MODERATE_RISK_THRESHOLD => InsightType::WARNING->value,
            $atRiskCount > 0 => InsightType::INFO->value,
            default => InsightType::SUCCESS->value,
        };
    }

    private function generateTitle(float $score): string
    {
        return match (true) {
            $score >= self::HIGH_RISK_THRESHOLD => 'High Risk Alert',
            $score >= self::MODERATE_RISK_THRESHOLD => 'Moderate Risk',
            $score > 0 => 'Low Risk',
            default => 'No Risk Detected',
        };
    }

    private function generateMessage(float $score, int $atRiskCount, int $dueSoonCount): string
    {
        if ($score === 0.0) {
            return $dueSoonCount > 0
                ? sprintf('No risks detected. All %d upcoming tasks have recent activity.', $dueSoonCount)
                : 'No upcoming deadlines or risks detected.';
        }

        return sprintf(
            'Risk score: %.1f%% - %d of %d upcoming tasks lack recent activity.',
            $score, $atRiskCount, $dueSoonCount
        );
    }
}
