<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Project\ProjectCollectionResource;
use App\Services\Api\V1\DashboardService;
use Illuminate\Http\JsonResponse;

final class DashboardProjectsController extends ApiController
{
    public function __invoke(DashboardService $dashboardService): JsonResponse
    {
        $projects = $dashboardService->getDashboardProjects();

        return response()->json([
            'projects' => ProjectCollectionResource::collection($projects),
            'projectsCount' => $projects->count(),
            'message' => $projects->isEmpty() ? 'No active projects found' : '',
        ]);
    }
}
