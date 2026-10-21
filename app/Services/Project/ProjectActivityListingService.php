<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Models\Activity;
use App\Models\Project;
use App\Repository\ActivityRepository;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ProjectActivityListingService
{
    public function __construct(private ActivityRepository $activityRepository) {}

    /**
     * @param  array<string, mixed>  $paginationQuery
     * @return LengthAwarePaginator<int, Activity>
     */
    public function paginate(
        Project $project,
        ?string $filterType,
        ?int $actorId,
        int $perPage,
        int $page,
        array $paginationQuery = [],
    ): LengthAwarePaginator {
        $activitiesQuery = $this->activityRepository->filterActivities(
            $project->activities()->getQuery(),
            $filterType,
            $actorId,
        )->with([
            'user:id,uuid,name,username,avatar_path',
            'subject' => fn (Relation $relation) => $relation instanceof MorphTo ? $relation->withTrashed() : $relation,
        ]);

        /** @var \Illuminate\Database\Eloquent\Builder<Activity> $activitiesQuery */
        return $activitiesQuery->paginate($perPage, ['*'], 'page', $page)->appends($paginationQuery);
    }
}
