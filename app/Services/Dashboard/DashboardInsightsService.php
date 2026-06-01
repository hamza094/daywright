<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\InsightPriority;
use App\Enums\InsightType;
use App\Enums\StatusLevel;
use App\Repository\DashboardInsightsRepository;
use Illuminate\Support\Collection;

final readonly class DashboardInsightsService
{
    public function __construct(
        private DashboardInsightsRepository $insightsRepository
    ) {}

    /**
     * Build KPI cards for the dashboard
     *
     * @return array<string, array{value:int|float, label:string, status:string}>
     */
    public function getKPIs(int $userId): array
    {
        $userProjects = $this->insightsRepository->getUserProjects($userId);
        $overdue = $this->insightsRepository->getOverdueTasksCount($userId);
        $criticalProjects = $this->insightsRepository->getCriticalProjectsCount($userProjects);
        $totalProjects = $userProjects->count();

        $completionRate = $this->insightsRepository->getTaskCompletionRate($userId);

        return [
            'total_projects' => [
                'value' => $totalProjects,
                'label' => config('dashboard.labels.kpis.total_projects', 'Active Projects'),
                'status' => $totalProjects > 0 ? 'good' : 'warning',
            ],
            'critical_projects' => [
                'value' => $criticalProjects,
                'label' => config('dashboard.labels.kpis.critical_projects', 'Projects Need Attention'),
                'status' => $this->getCriticalProjectsStatus($criticalProjects),
            ],
            'overdue_tasks' => [
                'value' => $overdue,
                'label' => config('dashboard.labels.kpis.overdue_tasks', 'Total Overdue Tasks'),
                'status' => $this->getOverdueStatus($overdue),
            ],
            'completion_rate' => [
                'value' => $completionRate,
                'label' => config('dashboard.labels.kpis.completion_rate', 'Overall Completion Rate'),
                'status' => $this->getCompletionRateStatus($completionRate),
            ],
        ];
    }

    /**
     * Build actionable insights list
     *
     * @return array<int, array{type:string, title:string, message:string, action:string, priority:string}>
     */
    public function getActionableInsights(int $userId): array
    {
        $userProjects = $this->insightsRepository->getUserProjects($userId);
        $overdueCount = $this->insightsRepository->getOverdueTasksCount($userId);
        $criticalProjects = $this->insightsRepository->getCriticalProjectsCount($userProjects);

        return [
            ...$this->buildOverdueInsights($overdueCount),
            ...$this->buildCriticalProjectsInsights($criticalProjects),
            ...$this->buildPortfolioInsights($userProjects, $criticalProjects, $overdueCount),
        ];
    }

    /**
     * @return array<int, array{type:string, title:string, message:string, action:string, priority:string}>
     */
    private function buildOverdueInsights(int $overdueCount): array
    {
        $insights = [];
        $criticalThreshold = (int) config('dashboard.insights.overdue_tasks.critical_threshold', 10);

        if ($overdueCount > $criticalThreshold) {
            $insights[] = $this->makeInsight(
                InsightType::CRITICAL->value,
                config('dashboard.labels.insights.high_overdue_title', 'High Overdue Task Count'),
                "{$overdueCount} tasks are overdue across your projects",
                'Review and prioritize overdue tasks immediately',
                InsightPriority::CRITICAL->value
            );
        } elseif ($overdueCount > 0) {
            $insights[] = $this->makeInsight(
                InsightType::WARNING->value,
                config('dashboard.labels.insights.overdue_detected_title', 'Overdue Tasks Detected'),
                "{$overdueCount} tasks need attention",
                'Check individual projects for overdue items',
                InsightPriority::HIGH->value
            );
        }

        return $insights;
    }

    /**
     * @return array<int, array{type:string, title:string, message:string, action:string, priority:string}>
     */
    private function buildCriticalProjectsInsights(int $criticalProjects): array
    {
        if ($criticalProjects <= 0) {
            return [];
        }

        return [
            $this->makeInsight(
                InsightType::WARNING->value,
                config('dashboard.labels.insights.projects_need_attention_title', 'Projects Need Attention'),
                "{$criticalProjects} projects have critical issues",
                'Review individual project insights for details',
                InsightPriority::HIGH->value
            ),
        ];
    }

    /**
     * @param  Collection<int, \App\Models\Project>  $userProjects
     * @return array<int, array{type:string, title:string, message:string, action:string, priority:string}>
     */
    private function buildPortfolioInsights(Collection $userProjects, int $criticalProjects, int $overdueCount): array
    {
        if ($userProjects->count() === 0) {
            return [
                $this->makeInsight(
                    InsightType::INFO->value,
                    config('dashboard.labels.insights.no_active_title', 'No Active Projects'),
                    'You have no active projects',
                    'Create your first project to get started',
                    InsightPriority::MEDIUM->value
                ),
            ];
        }

        if (($criticalProjects + $overdueCount) === 0) {
            return [
                $this->makeInsight(
                    InsightType::SUCCESS->value,
                    config('dashboard.labels.insights.portfolio_healthy_title', 'Portfolio Healthy'),
                    'All projects are on track with no overdue tasks',
                    'Keep up the excellent work',
                    InsightPriority::LOW->value
                ),
            ];
        }

        return [];
    }

    private function getOverdueStatus(int $overdueCount): string
    {
        return StatusLevel::fromOverdue($overdueCount)->value;
    }

    private function getCriticalProjectsStatus(int $criticalCount): string
    {
        return StatusLevel::fromCriticalProjects($criticalCount)->value;
    }

    private function getCompletionRateStatus(float $completionRate): string
    {
        return StatusLevel::fromCompletionRate($completionRate)->value;
    }

    /**
     * Helper to build standardized insight items
     *
     * @return array{type:string, title:string, message:string, action:string, priority:string}
     */
    private function makeInsight(string $type, string $title, string $message, string $action, string $priority = 'medium'): array
    {
        return ['type' => $type, 'title' => $title, 'message' => $message, 'action' => $action, 'priority' => $priority];
    }
}
