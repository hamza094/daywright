<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Models\Activity;
use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectActivityListingService
{
    public function __construct(private readonly ProjectActivityQueryFilter $projectActivityQueryFilter) {}

    /**
     * @return LengthAwarePaginator<int, Activity>
     */
    public function paginate(
        Project $project,
        ?string $filterType,
        ?int $actorId,
        int $perPage,
        int $page,
        string $path,
    ): LengthAwarePaginator {
        $activitiesQuery = $this->projectActivityQueryFilter->filterActivities(
            $project->activities()->getQuery(),
            $filterType,
            $actorId,
        )->with([
            'user:id,uuid,name,username,avatar_path',
            'subject' => fn ($query) => $query->withTrashed(),
        ]);

        $paginator = $activitiesQuery->paginate($perPage, ['*'], 'page', $page);
        $paginator->withPath($path);

        return $paginator;
    }
}
