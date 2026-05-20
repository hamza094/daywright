<?php

declare(strict_types=1);

namespace App\Repository\Admin;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class DashboardRepository
{
    /**
     * @return EloquentCollection<int, Activity>
     */
    public function recentActivities(int $limit = 15): EloquentCollection
    {
        return Activity::query()
            ->with('user', 'subject', 'project')
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function fetchDataForMonths($startDate, $endDate): array
    {
        $projectsData = DB::table('projects')
            ->selectRaw('
         DATE_FORMAT(created_at, ?) as month,
         COUNT(*) as total_projects,
         SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) AS active_projects,
         SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) AS trashed_projects
         ', ['%Y-%m'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $tasksData = DB::table('tasks')
            ->selectRaw('
        DATE_FORMAT(created_at, ?) as month,
        COUNT(*) as total_tasks,
        SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) AS active_tasks,
        SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) AS trashed_tasks
    ', ['%Y-%m'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return ['projectsData' => $projectsData, 'tasksData' => $tasksData];
    }
}
