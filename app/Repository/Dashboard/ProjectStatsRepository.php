<?php

declare(strict_types=1);

namespace App\Repository\Dashboard;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class ProjectStatsRepository
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
     * Build the base project query used for stats.
     *
     * @return EloquentBuilder<Project>
     */
    private function buildUserProjectQuery(int $userId, ?int $year, ?int $month): EloquentBuilder
    {
        return Project::withTrashed()
            ->leftJoin('project_members as pm', function ($join) use ($userId): void {
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
     *
     * @param  EloquentBuilder<Project>  $query
     */
    private function selectProjectStats(EloquentBuilder $query, int $userId): ?object
    {
        return $query->toBase()->selectRaw(
            'SUM(CASE WHEN projects.user_id = ? AND projects.deleted_at IS NULL THEN 1 ELSE 0 END) AS active_projects,
             SUM(CASE WHEN projects.user_id = ? AND projects.deleted_at IS NOT NULL THEN 1 ELSE 0 END) AS trashed_projects,
             SUM(CASE WHEN pm.user_id IS NOT NULL AND projects.user_id != ? AND projects.deleted_at IS NULL THEN 1 ELSE 0 END) AS member_projects',
            [$userId, $userId, $userId]
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
}
