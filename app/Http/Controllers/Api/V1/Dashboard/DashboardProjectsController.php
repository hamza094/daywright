<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Project\ProjectCollectionResource;
use App\Services\Dashboard\UserProjectListingService;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;

final class DashboardProjectsController extends ApiController
{
    /**
     * Return recent dashboard projects along with a top-level total count.
     *
     * Returns the latest dashboard projects and includes a top-level total count in the response meta.
     */
    #[Endpoint(operationId: 'dashboard.projects')]
    public function __invoke(UserProjectListingService $userProjectListingService): JsonResponse
    {
        $projects = $userProjectListingService->getDashboardProjects($this->authenticatedUser());

        return ProjectCollectionResource::collection($projects)
            ->additional([
                'meta' => [
                    'total' => $projects->count(),
                ],
            ])
            ->response();
    }
}
