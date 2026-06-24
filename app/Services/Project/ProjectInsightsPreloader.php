<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Models\Project;
use Carbon\CarbonInterface;
use LogicException;

/**
 * Preloads (primes) aggregated counts & lightweight aggregates required by metrics/insights.
 * Replaces the former ProjectInsightsPrimingService.
 */
class ProjectInsightsPreloader
{
    /** @var array<string,mixed>|null */
    private ?array $cachedWindowConfig = null;

    /**
     * @param  array<string>  $sections
     */
    public function preload(Project $project, array $sections): void
    {
        $now = now();
        $expandedSections = $this->expandSections($sections);

        $countLoaders = $this->buildCountLoaders($expandedSections, $now);
        if ($countLoaders !== []) {
            $project->loadCount($countLoaders);
        }

        if ($this->needsRecentParticipants($expandedSections)) {
            $this->attachRecentParticipants($project, $now);
        }
    }

    public function preloadForHealth(Project $project): void
    {
        $this->preload($project, ['health']);
    }

    /**
     * @param  array<int|string,string>  $sections
     * @return array<int|string,mixed>
     */
    private function buildCountLoaders(array $sections, CarbonInterface $now): array
    {
        /** @var array<string, array<int,string>> $sectionToTypesMap */
        $sectionToTypesMap = (array) config('insights.section_count_mappings', []);

        /** @var \Illuminate\Support\Collection<int,string> $metricTypes */
        $metricTypes = collect($sections)
            ->flatMap(fn (string $section): array =>
                /** @var array<int,string> $types */
                $sectionToTypesMap[$section] ?? [])
            ->unique()
            ->values();

        if ($metricTypes->isEmpty() && in_array('health', $sections, true)) {
            $metricTypes = collect(['tasks', 'communication', 'collaboration', 'activity']);
        }

        if ($metricTypes->isEmpty()) {
            return [];
        }

        $windowConfig = $this->loadConfigWindowValues();

        $loaders = [];
        foreach ($metricTypes as $metricType) {
            foreach ($this->definitionsForMetricType($metricType, $now, $windowConfig) as $key => $definition) {
                if (array_key_exists($key, $loaders) && config('app.debug')) {
                    throw new LogicException("Duplicate count key '{$key}' while resolving project insight counts");
                }
                $loaders[$key] = $definition;
            }
        }

        return $loaders;
    }

    /**
     * @param  array<string,int>  $windowConfig
     * @return array<int|string,mixed>
     */
    private function definitionsForMetricType(string $metricType, CarbonInterface $now, array $windowConfig): array
    {
        return match ($metricType) {
            'tasks' => [
                'tasks' => fn ($q) => $q->withTrashed(),
                'tasks as tasks_count' => fn ($q) => $q->whereNull('deleted_at'),
                'tasks as completed_tasks_count' => fn ($q) => $q->completed(),
                'tasks as overdue_tasks_count' => fn ($q) => $q->overdue(),
                'tasks as abandoned_tasks_count' => fn ($q) => $q->onlyTrashed(),
            ],
            'communication' => [
                'conversations as recent_conversations_count' => fn ($q) => $q->where('created_at', '>=', (clone $now)->subDays($windowConfig['conversationLookbackDays'])),
            ],
            'collaboration' => [
                'activeMembers as active_members_count' => fn ($q) => $q,
                'meetings as recent_meetings_count' => fn ($q) => $q->where('start_time', '>=', (clone $now)->subDays($windowConfig['meetingLookbackDays'])),
            ],
            'activity' => [
                'activities as recent_activities_count' => fn ($q) => $q->where('created_at', '>=', (clone $now)->subDays($windowConfig['recentActivityLookbackDays'])),
            ],
            'risk' => [
                'tasks as tasks_due_soon_count' => fn ($q) => $q->dueSoon($windowConfig['riskAssessmentHours']),
                'tasks as tasks_at_risk_count' => fn ($q) => $q->dueSoon($windowConfig['riskAssessmentHours'])->whereDoesntHave('activities', fn ($sub) => $sub->where('created_at', '>=', (clone $now)->subDays($windowConfig['taskInactivityDays']))),
            ],
            default => [],
        };
    }

    /**
     * @return array<string,int>
     */
    private function loadConfigWindowValues(): array
    {
        if ($this->cachedWindowConfig !== null) {
            return $this->cachedWindowConfig;
        }

        return $this->cachedWindowConfig = [
            'conversationLookbackDays' => $this->getConfigValue('time_periods.conversation_lookback_days', (int) config('insights.time_periods.conversation_lookback_days', 14)),
            'meetingLookbackDays' => $this->getConfigValue('time_periods.meeting_lookback_days', (int) config('insights.time_periods.meeting_lookback_days', 14)),
            'collaborationActivityLookbackDays' => $this->getConfigValue('time_periods.collaboration_activity_days', (int) config('insights.time_periods.collaboration_activity_days', 30)),
            'recentActivityLookbackDays' => $this->getConfigValue('time_periods.recent_activity_days', (int) config('insights.time_periods.recent_activity_days', 30)),
            'riskAssessmentHours' => $this->getConfigValue('time_periods.risk_assessment_hours', (int) config('insights.time_periods.risk_assessment_hours', 72)),
            'taskInactivityDays' => $this->getConfigValue('time_periods.task_inactivity_days', (int) config('insights.time_periods.task_inactivity_days', 5)),
        ];
    }

    private function attachRecentParticipants(Project $project, CarbonInterface $now): void
    {
        $cfg = $this->loadConfigWindowValues();

        $recentParticipants = $project->activities()
            ->where('created_at', '>=', (clone $now)->subDays($cfg['collaborationActivityLookbackDays']))
            ->distinct('user_id')
            ->count('user_id');

        $project->setAttribute('recent_participants_count', $recentParticipants);
    }

    /**
     * @param  array<int|string,string>  $sections
     * @return array<int,string>
     */
    private function expandSections(array $sections): array
    {
        if (! in_array('health', $sections, true)) {
            return $sections;
        }

        return collect($sections)
            ->merge(['tasks', 'communication', 'collaboration', 'activity'])
            ->unique()
            ->values()
            ->all();
    }

    private function getConfigValue(string $key, mixed $default): mixed
    {
        return config("insights.{$key}", config("project-metrics.{$key}", $default));
    }

    /** @param array<string> $sections */
    private function needsRecentParticipants(array $sections): bool
    {
        return in_array('collaboration', $sections, true) || in_array('health', $sections, true);
    }
}
