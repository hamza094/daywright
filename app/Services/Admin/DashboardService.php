<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repository\Admin\DashboardRepository;

class DashboardService
{
    public function __construct(protected DashboardRepository $dashboardRepository) {}

    /**
     * @return array<mixed, array<'active_projects'|'active_tasks'|'month'|'projects_count'|'tasks_count'|'trashed_projects'|'trashed_tasks', mixed>>
     */
    public function fetchDataForMonths($startDate, $endDate): array
    {
        $data = [];
        $result = $this->dashboardRepository->fetchDataForMonths($startDate, $endDate);

        foreach ($result['projectsData'] as $project) {
            $data[] = [
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
            $monthIndex = array_search($task->month, array_column($data, 'month'));

            if ($monthIndex !== false) {
                $data[$monthIndex]['active_tasks'] = $task->active_tasks;
                $data[$monthIndex]['trashed_tasks'] = $task->trashed_tasks;
                $data[$monthIndex]['tasks_count'] = $task->total_tasks;
            } else {
                $data[] = [
                    'month' => $task->month,
                    'active_projects' => 0,
                    'trashed_projects' => 0,
                    'tasks_count' => $task->total_tasks,
                    'active_tasks' => $task->active_tasks,
                    'trashed_tasks' => $task->trashed_tasks,
                ];
            }
        }

        return $data;
    }
}
