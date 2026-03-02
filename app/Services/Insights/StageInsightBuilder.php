<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Enums\InsightType;
use App\Enums\ProjectStage;
use Override;

final class StageInsightBuilder implements InsightBuilderInterface
{
    private const int SUCCESS_THRESHOLD = 70;

    private const int INFO_THRESHOLD = 40;

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    #[Override]
    public function build(mixed $input, array $context = []): array
    {
        if (! is_array($input) || ! isset($input['percentage'])) {
            return [
                'type' => InsightType::INFO->value,
                'title' => 'No Stage Data',
                'message' => 'No stage information available.',
                'data' => ['value' => null],
            ];
        }

        $percentage = (float) max(0, min(100, $input['percentage']));
        $currentStage = $input['current_stage'] ?? 'Unknown';
        $status = $input['status'] ?? 'unknown';
        $stageId = $input['stage_id'] ?? null;

        // Try to use enum if stage_id is available
        $stageEnum = $stageId ? ProjectStage::tryFrom($stageId) : null;

        return [
            'type' => $this->determineInsightType($percentage, $status),
            'title' => $this->generateTitle($stageEnum, $percentage),
            'message' => $this->generateMessage($stageEnum, $currentStage, $percentage, $status),
            'data' => [
                'value' => $percentage,
                'stage' => $currentStage,
            ],
        ];
    }

    private function determineInsightType(float $percentage, string $status): string
    {
        if ($status === 'completed') {
            return InsightType::SUCCESS->value;
        }

        if ($status === 'postponed') {
            return InsightType::WARNING->value;
        }

        return match (true) {
            $percentage >= self::SUCCESS_THRESHOLD => InsightType::SUCCESS->value,
            $percentage >= self::INFO_THRESHOLD => InsightType::INFO->value,
            default => InsightType::WARNING->value,
        };
    }

    private function generateTitle(?ProjectStage $stageEnum, float $percentage): string
    {
        if ($stageEnum instanceof ProjectStage) {
            return match ($stageEnum) {
                ProjectStage::Completed => 'Project Completed',
                ProjectStage::Postponed => 'Project Postponed',
                ProjectStage::Delivery => 'Near Completion',
                ProjectStage::Testing => 'Quality Assurance',
                ProjectStage::Development => 'Active Development',
                ProjectStage::Design => 'Design Phase',
                ProjectStage::Planning => 'Planning Phase',
            };
        }

        return match (true) {
            $percentage >= self::SUCCESS_THRESHOLD => 'Good Progress',
            $percentage >= self::INFO_THRESHOLD => 'Moderate Progress',
            default => 'Early Stage',
        };
    }

    private function generateMessage(?ProjectStage $stageEnum, string $stageLabel, float $percentage, string $status): string
    {
        if ($status === 'completed') {
            return 'Project completed successfully (100% progress).';
        }

        if ($status === 'postponed') {
            return sprintf('Project postponed in %s stage.', $stageLabel);
        }

        if ($stageEnum instanceof ProjectStage) {
            return match ($stageEnum) {
                ProjectStage::Delivery => sprintf('Delivery phase (%.1f%% complete) - preparing for launch.', $percentage),
                ProjectStage::Testing => sprintf('Testing phase (%.1f%% complete) - quality assurance underway.', $percentage),
                ProjectStage::Development => sprintf('Development phase (%.1f%% complete) - core implementation in progress.', $percentage),
                ProjectStage::Design => sprintf('Design phase (%.1f%% complete) - planning and wireframing active.', $percentage),
                ProjectStage::Planning => sprintf('Planning phase (%.1f%% complete) - project setup and requirements gathering.', $percentage),
                default => sprintf('In %s stage (%.1f%% complete).', $stageLabel, $percentage),
            };
        }

        return sprintf('In %s stage (%.1f%% complete).', $stageLabel, $percentage);
    }
}
