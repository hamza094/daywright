<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Actions\ProjectMetrics\TaskHealthMetricAction;
use App\Data\ProjectMetricsDto;
use App\Models\Project;
use App\Repository\ProjectInsightsRepository;
use App\Services\Insights\HealthInsightBuilder;
use App\Services\Insights\RiskInsightBuilder;
use App\Services\Insights\StageInsightBuilder;
use App\Services\Insights\TaskHealthInsightBuilder;
use App\Services\Insights\TeamCollaborationInsightBuilder;

// Communication and activity metric actions are not used by this service; removed to satisfy static analysis

final class ProjectInsightService
{
    /**
     * @var array<string, callable(ProjectMetricsDto, ?Project): mixed>
     */
    private array $insightBuilders;

    public function __construct(
        private readonly ProjectInsightsRepository $repository,
        private readonly TaskHealthMetricAction $taskHealthAction,
        private readonly HealthInsightBuilder $healthBuilder,
        private readonly TaskHealthInsightBuilder $taskHealthBuilder,
        private readonly TeamCollaborationInsightBuilder $collaborationBuilder,
        private readonly RiskInsightBuilder $riskBuilder,
        private readonly StageInsightBuilder $stageBuilder,
    ) {
        $this->setupBuilders();
    }

    /**
     * Get comprehensive project insights with actionable recommendations
     *
     * @param  array<string>  $sections
     * @return array<string,mixed>
     */
    public function getInsights(Project $project, array $sections = []): array
    {
        if ($sections === []) {
            $sections = array_keys($this->insightBuilders);
        }

        $metrics = $this->repository->getProjectInsights($project, $sections);

        return $this->buildInsights($metrics, $sections, $project);
    }

    /**
     * Setup insight builders mapping
     */
    private function setupBuilders(): void
    {
        // Each builder receives the metrics DTO and an optional Project instance
        $this->insightBuilders = [
            'health' => fn (ProjectMetricsDto $m, ?Project $project = null): array => $this->healthBuilder->build($m->health),

            'task-health' => fn (ProjectMetricsDto $m, ?Project $project = null): array => $this->taskHealthBuilder->build(
                $m->taskHealth,
                $project instanceof Project ? ['summary' => $this->taskHealthAction->summary($project)] : []
            ),
            'collaboration' => fn (ProjectMetricsDto $m, ?Project $project = null): array => $this->collaborationBuilder->build(
                $m->collaborationScore,
                ['details' => $this->getCollaborationDetails($project)]
            ),
            'risk' => fn (ProjectMetricsDto $m, ?Project $project = null): array => $this->riskBuilder->build($m->upcomingRisk),
            'stage' => fn (ProjectMetricsDto $m, ?Project $project = null): array => $this->stageBuilder->build($m->stageProgress),
        ];
    }

    /**
     * Generate actionable insights based on project data
     *
     * @param  array<string>  $sections
     * @return array<string,mixed>
     */
    private function buildInsights(ProjectMetricsDto $metrics, array $sections, ?Project $project = null): array
    {
        return collect($this->insightBuilders)
            ->filter(fn ($builder, $section): bool => $this->shouldIncludeInsight($section, $sections))
            ->filter(fn ($builder, $section): bool => $this->hasData($metrics, $section))
            ->map(fn ($builder): mixed => $builder($metrics, $project))
            ->values()
            ->toArray();
    }

    /**
     * Check if insight should be included based on sections
     */
    /**
     * @param  array<string>  $sections
     */
    private function shouldIncludeInsight(string $section, array $sections): bool
    {
        return in_array($section, $sections, true);
    }

    /**
     * Check if metrics has data for the given section
     */
    private function hasData(ProjectMetricsDto $metrics, string $section): bool
    {
        return match ($section) {
            'health' => $metrics->health !== null,
            'task-health' => $metrics->taskHealth !== null,
            'collaboration' => $metrics->collaborationScore !== null,
            'risk' => $metrics->upcomingRisk !== null,
            'stage' => $metrics->stageProgress !== null,
            default => false,
        };
    }

    /**
     * Extract collaboration details from project for insight building
     */
    /**
     * @return array<string,int|float>
     */
    private function getCollaborationDetails(?Project $project): array
    {
        if (! $project instanceof Project) {
            return [];
        }

        return [
            'member_count' => max(0, (int) ($project->active_members_count ?? 0)),
            'meeting_count' => max(0, (int) ($project->recent_meetings_count ?? 0)),
            'participant_count' => max(0, (int) ($project->recent_participants_count ?? 0)),
        ];
    }
}
