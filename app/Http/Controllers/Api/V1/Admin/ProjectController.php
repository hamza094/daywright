<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Project\BuildAdminProjectAppliedFiltersAction;
use App\Actions\Project\BulkDeleteProjectsAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Admin\ProjectBulkDeleteRequest;
use App\Http\Requests\Api\V1\Admin\ProjectFilterRequest;
use App\Http\Resources\Api\V1\Admin\ProjectResource;
use App\Repository\Admin\ProjectFiltersRepository;
use Illuminate\Http\JsonResponse;

class ProjectController extends ApiController
{
    public function index(
        ProjectFilterRequest $request,
        ProjectFiltersRepository $repository,
        BuildAdminProjectAppliedFiltersAction $buildAppliedFilters,
    ): JsonResponse {
        $filters = $request->filters();

        $projects = $repository->filter($filters, $request->perPage());
        $appliedFilters = $buildAppliedFilters->execute($filters);

        $this->authenticatedUser()->load('oauthConnections');

        return ProjectResource::collection($projects)
            ->additional([
                'meta' => [
                    'applied_filters' => $appliedFilters,
                ],
            ])
            ->response();
    }

    public function bulkDelete(
        ProjectBulkDeleteRequest $request,
        BulkDeleteProjectsAction $bulkDeleteProjectsAction,
    ): JsonResponse {
        $projectIds = $request->validated('project_ids');

        $bulkDeleteProjectsAction->execute($projectIds);

        return $this->respondWithMessage('Projects deleted successfully.');
    }
}
