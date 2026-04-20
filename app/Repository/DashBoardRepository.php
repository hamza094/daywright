<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class DashBoardRepository
{
    /**
     * @return array{active_projects:int, trashed_projects:int, member_projects:int, total_projects:int}
     */
    public function getProjectStats(int $userId, ?int $year = null, ?int $month = null): array
    {
        $query = $this->buildUserProjectQuery($userId, $year, $month);
        $result = $this->selectProjectStats($query, $userId);

        return $this->formatProjectStats($result);
    }

    /**
     * Get user activities for a specific date range
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    /**
     * @return EloquentCollection<int, Activity>
     */
    public function getUserActivities(int $userId, Carbon $startDate, Carbon $endDate)
    {
        return Activity::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with([
                'subject',
                'project' => function (BelongsTo $query): void {
                    $query->withTrashed()
                        ->select([
                            'id',
                            'name',
                            'slug',
                            'stage_id',
                            'created_at',
                        ]);
                },
                'project.stage',
            ])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Build the base project query used for stats.
     */
    private function buildUserProjectQuery(int $userId, ?int $year, ?int $month): EloquentBuilder
    {
        return Project::leftJoin('project_members as pm', function ($join) use ($userId): void {
            $join->on('projects.id', '=', 'pm.project_id')
                ->where('pm.user_id', $userId)
                ->where('pm.active', 1);
        })
            ->where(function (EloquentBuilder $query) use ($userId): void {
                $query->where('projects.user_id', $userId)
                    ->orWhere('pm.user_id', $userId);
            })
            ->createdIn($year, $month);
    }

    /**
     * Apply the stats select and return the raw result object.
     */
    private function selectProjectStats(EloquentBuilder $query, int $userId): ?object
    {
        return $query->toBase()->selectRaw(
            'SUM(CASE WHEN projects.user_id = ? AND projects.deleted_at IS NULL THEN 1 ELSE 0 END) AS active_projects,
             SUM(CASE WHEN projects.user_id = ? AND projects.deleted_at IS NOT NULL THEN 1 ELSE 0 END) AS trashed_projects,
             SUM(CASE WHEN pm.user_id IS NOT NULL AND projects.deleted_at IS NULL THEN 1 ELSE 0 END) AS member_projects',
            [$userId, $userId]
        )->first();
    }

    /**
     * Normalize the DB result into the expected array shape.
     *
     * @return array{active_projects:int, trashed_projects:int, member_projects:int, total_projects:int}
     */
    private function formatProjectStats(?object $result): array
    {
        $active = (int) ($result->active_projects ?? 0);
        $trashed = (int) ($result->trashed_projects ?? 0);
        $member = (int) ($result->member_projects ?? 0);

        return [
            'active_projects' => $active,
            'trashed_projects' => $trashed,
            'member_projects' => $member,
            'total_projects' => $active + $trashed + $member,
        ];
    }

    /*public function fetchTaskStatistics(): object
    {
        $userId = Auth::id();

        return DB::table('tasks')
            ->selectRaw("
                COUNT(*) as total_tasks,
                SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) AS completed_tasks,
                SUM(CASE WHEN completed = 0 AND due_date < NOW() THEN 1 ELSE 0 END) AS overdue_tasks,
                SUM(CASE WHEN completed = 0 AND due_date >= NOW() THEN 1 ELSE 0 END) AS pending_tasks,
                AVG(CASE WHEN completed = 1 THEN DATEDIFF(updated_at, created_at) END) AS avg_completion_days
            ")
            ->where(function($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhere('assignee_id', $userId);
            })
            ->first();
    }*/

    /*public function fetchProductivityMetrics(): array
    {
        $userId = Auth::id();
        $lastMonth = Carbon::now()->subMonth();

        return [
            'tasks_completed_this_month' => Task::where('user_id', $userId)
                ->where('completed', 1)
                ->where('updated_at', '>=', $lastMonth)
                ->count(),
            'projects_created_this_month' => Project::where('user_id', $userId)
                ->where('created_at', '>=', $lastMonth)
                ->count(),
            'avg_task_completion_time' => Task::where('user_id', $userId)
                ->where('completed', 1)
                ->whereNotNull('updated_at')
                ->avg(DB::raw('DATEDIFF(updated_at, created_at)')),
            'most_active_project' => $this->getMostActiveProject($userId)
        ];
    }*/

    /*private function getMostActiveProject(int $userId): ?object
    {
        return DB::table('activities')
            ->join('projects', 'activities.project_id', '=', 'projects.id')
            ->selectRaw("
                projects.name as project_name,
                COUNT(*) as activity_count
            ")
            ->where('activities.user_id', $userId)
            ->whereNull('projects.deleted_at')
            ->groupBy('projects.id', 'projects.name')
            ->orderBy('activity_count', 'desc')
            ->first();
    }*/
}
