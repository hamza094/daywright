<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Activity;
use App\Repository\Admin\DashboardRepository;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class DashboardService
{
    public function __construct(protected DashboardRepository $dashboardRepository) {}

    /**
     * @return EloquentCollection<int, Activity>
     */
    public function recentActivities(int $limit = 15): EloquentCollection
    {
        return $this->dashboardRepository->recentActivities($limit);
    }

    /**
     * @return array<mixed, array<'active_projects'|'active_tasks'|'month'|'projects_count'|'tasks_count'|'trashed_projects'|'trashed_tasks', mixed>>
     */
    public function fetchDataForMonths(DateTimeInterface|string $startDate, DateTimeInterface|string $endDate): array
    {
        $data = [];
        $result = $this->dashboardRepository->fetchDataForMonths($startDate, $endDate);

        // Index by month to avoid repeated array searches (O(n^2)).
        foreach ($result['projectsData'] as $project) {
            $data[$project->month] = [
                'month' => $project->month,
                'projects_count' => $project->total_projects,
                'active_projects' => $project->active_projects,
                'trashed_projects' => $project->trashed_projects,
                'tasks_count' => 0,
                'active_tasks' => 0,
                'trashed_tasks' => 0,
            ];
        }

        foreach ($result['tasksData'] as $task) {
            if (isset($data[$task->month])) {
                $data[$task->month]['active_tasks'] = $task->active_tasks;
                $data[$task->month]['trashed_tasks'] = $task->trashed_tasks;
                $data[$task->month]['tasks_count'] = $task->total_tasks;
            } else {
                $data[$task->month] = [
                    'month' => $task->month,
                    'active_projects' => 0,
                    'trashed_projects' => 0,
                    'tasks_count' => $task->total_tasks,
                    'active_tasks' => $task->active_tasks,
                    'trashed_tasks' => $task->trashed_tasks,
                ];
            }
        }

        return array_values($data);
    }
}
