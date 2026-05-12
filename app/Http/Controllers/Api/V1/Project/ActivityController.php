<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\ProjectActivityIndexRequest;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Project;
use App\Repository\ProjectRepository;
use App\Services\PaginationService;
use Illuminate\Http\JsonResponse;

class ActivityController extends ApiController
{
    public function index(Project $project, ProjectActivityIndexRequest $request, ProjectRepository $repository): JsonResponse
    {
        $activities = $repository->filterActivities(
            $project->activities,
            $request->filterType(),
        );

        $page = (int) $request->validated('page', 1);
        $perPage = $request->perPage();

        $paginatedActivities = new PaginationService(
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
