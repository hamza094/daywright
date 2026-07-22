<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Actions\ProjectMetrics\ProjectHealthMetricAction;
use App\Actions\ProjectMetrics\StageProgressMetricAction;
use App\Actions\ProjectMetrics\TaskHealthMetricAction;
use App\Actions\ProjectMetrics\TeamCollaborationMetricAction;
use App\Actions\ProjectMetrics\UpcomingRiskMetricAction;
use App\DataTransferObjects\Project\ProjectMetricsData;
use App\Models\Project;
use App\Services\Insights\HealthInsightBuilder;
use App\Services\Insights\RiskInsightBuilder;
use App\Services\Insights\StageInsightBuilder;
use App\Services\Insights\TaskHealthInsightBuilder;
use App\Services\Insights\TeamCollaborationInsightBuilder;

final class ProjectInsightsService
{
    /**
     * @var array<string, callable(ProjectMetricsDto, ?Project): mixed>
     */
    private array $insightBuilders;

    public function __construct(
        private readonly ProjectHealthMetricAction $projectHealthAction,
        private readonly TaskHealthMetricAction $taskHealthAction,
        private readonly StageProgressMetricAction $stageProgressAction,
        private readonly UpcomingRiskMetricAction $upcomingRiskAction,
        private readonly TeamCollaborationMetricAction $collaborationHealthAction,
        private readonly ProjectInsightsPreloader $preloader,
        private readonly HealthInsightBuilder $healthBuilder,
        private readonly TaskHealthInsightBuilder $taskHealthBuilder,
        private readonly TeamCollaborationInsightBuilder $collaborationBuilder,
        private readonly RiskInsightBuilder $riskBuilder,
        private readonly StageInsightBuilder $stageBuilder,
    ) {
        $this->setupBuilders();
    }

    /**
     * @param  array<string>  $sections
     * @return array<string,mixed>
     */
    public function getInsights(Project $project, array $sections = []): array
    {
        if ($sections === []) {
            $sections = array_keys($this->insightBuilders);
        }

        $metrics = $this->getProjectMetrics($project, $sections);

        return $this->buildInsights($metrics, $sections, $project);
    }

    /**
     * @param  array<string>  $sections
     */
    private function getProjectMetrics(Project $project, array $sections): ProjectMetricsData
    {
        $this->preloader->preload($project, $sections);

        $actions = [
            'health' => fn (): float => $this->projectHealthAction->execute($project),
            'task-health' => fn (): float => $this->taskHealthAction->execute($project),
            'risk' => fn (): array => $this->upcomingRiskAction->execute($project),
            'stage' => fn (): array => $this->stageProgressAction->execute($project),
            'collaboration' => fn (): float => $this->collaborationHealthAction->execute($project),
        ];

        $results = collect($actions)
            ->map(fn ($closure, $section): float|array|null => $this->shouldIncludeMetricSection($section, $sections) ? $closure() : null)
            ->values()
            ->all();

        return new ProjectMetricsData(...$results);
    }

    private function setupBuilders(): void
    {
        $this->insightBuilders = [
            'health' => fn (ProjectMetricsData $m, ?Project $project = null): array => $this->healthBuilder->build($m->health),

            'task-health' => fn (ProjectMetricsData $m, ?Project $project = null): array => $this->taskHealthBuilder->build(
                $m->taskHealth,
                $project instanceof Project ? ['summary' => $this->taskHealthAction->summary($project)] : []
            ),
            'collaboration' => fn (ProjectMetricsData $m, ?Project $project = null): array => $this->collaborationBuilder->build(
                $m->collaborationScore,
                ['details' => $this->getCollaborationDetails($project)]
            ),
            'risk' => fn (ProjectMetricsData $m, ?Project $project = null): array => $this->riskBuilder->build($m->upcomingRisk),
            'stage' => fn (ProjectMetricsData $m, ?Project $project = null): array => $this->stageBuilder->build($m->stageProgress),
        ];
    }

    /**
     * @param  array<string>  $sections
     * @return array<string,mixed>
     */
    private function buildInsights(ProjectMetricsData $metrics, array $sections, ?Project $project = null): array
    {
        return collect($this->insightBuilders)
            ->filter(fn ($builder, string $section): bool => $this->shouldIncludeInsight($section, $sections))
            ->filter(fn ($builder, string $section): bool => $this->hasData($metrics, $section))
            ->map(fn ($builder): mixed => $builder($metrics, $project))
            ->values()
            ->toArray();
    }

    /**
     * @param  array<string>  $sections
     */
    private function shouldIncludeInsight(string $section, array $sections): bool
    {
        return in_array($section, $sections, true);
    }

    /**
     * @param  array<string>  $sections
     */
    private function shouldIncludeMetricSection(string $section, array $sections): bool
    {
        return in_array('all', $sections, true) || in_array($section, $sections, true);
    }

    private function hasData(ProjectMetricsData $metrics, string $section): bool
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
