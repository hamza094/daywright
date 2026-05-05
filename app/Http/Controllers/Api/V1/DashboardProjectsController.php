<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Project\ProjectCollectionResource;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;

final class DashboardProjectsController extends ApiController
{
    public function __invoke(DashboardService $dashboardService): JsonResponse
    {
        $projects = $dashboardService->getDashboardProjects();

        return ProjectCollectionResource::collection($projects)
            ->additional([
                'meta' => [
                    'total' => $projects->count(),
                ],
            ])
            ->response();
    }
}
