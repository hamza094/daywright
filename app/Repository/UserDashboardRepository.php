<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Activity;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;

class UserDashboardRepository
{
    /**
     * @return array{active_projects:int, trashed_projects:int, member_projects:int, total_projects:int}
     */
    public function getProjectStats(int $userId, ?int $year = null, ?int $month = null): array
    {
        $query = Project::leftJoin('project_members as pm', function ($join) use ($userId): void {
            $join->on('projects.id', '=', 'pm.project_id')
                ->where('pm.user_id', $userId)
                ->where('pm.active', 1);
        })
            ->where(function ($query) use ($userId): void {
                $query->where('projects.user_id', $userId)
                    ->orWhere('pm.user_id', $userId);
            })
            ->createdIn($year, $month);

        $result = $query->selectRaw('
    SUM(CASE
        WHEN projects.user_id = ? AND projects.deleted_at IS NULL
        THEN 1 ELSE 0
    END) AS active_projects,
    SUM(CASE
        WHEN projects.user_id = ? AND projects.deleted_at IS NOT NULL
        THEN 1 ELSE 0
    END) AS trashed_projects,
    SUM(CASE
        WHEN pm.user_id IS NOT NULL AND projects.deleted_at IS NULL
        THEN 1 ELSE 0
    END) AS member_projects
', [$userId, $userId])
            ->first();

        return [
            'active_projects' => (int) ($result->active_projects ?? 0),
            'trashed_projects' => (int) ($result->trashed_projects ?? 0),
            'member_projects' => (int) ($result->member_projects ?? 0),
            'total_projects' => (int) (($result->active_projects ?? 0) + ($result->trashed_projects ?? 0) + ($result->member_projects ?? 0)),
        ];
    }

    /**
     * @return EloquentCollection<int, Activity>
     */
    public function getUserActivities(int $userId, Carbon $startDate, Carbon $endDate): EloquentCollection
    {
        $cacheKey = "activities_{$userId}_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addSeconds(60), fn (): EloquentCollection => Activity::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with([
                'subject',
                'project' => function ($query): void {
                    $query->withTrashed();
                },
                'project.stage',
            ])
            ->orderBy('created_at')
            ->get());
    }
}
