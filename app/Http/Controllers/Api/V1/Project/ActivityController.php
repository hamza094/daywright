<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\ProjectActivityIndexRequest;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Project;
use App\Services\ApiPaginator;
use App\Services\Project\ProjectActivityCollectionFilter;
use Illuminate\Http\JsonResponse;

class ActivityController extends ApiController
{
    /**
     * List project activity entries.
     *
     * Returns the released project activity feed with filter and pagination support.
     */
    public function index(Project $project, ProjectActivityIndexRequest $request, ProjectActivityCollectionFilter $projectActivityCollectionFilter): JsonResponse
    {
        $activities = $projectActivityCollectionFilter->filterActivities(
            $project->activities,
            $request->filterType(),
            $this->authenticatedUser()->id,
        );

        $page = (int) $request->validated('page', 1);
        $perPage = $request->perPage();

        $paginatedActivities = new ApiPaginator(
            $activities->forPage($page, $perPage)->values(),
            $activities->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ],
        );

        return $this->respondWithPaginatedData(
            ActivityResource::collection($paginatedActivities->getCollection()),
            $paginatedActivities,
        );
    }
}
