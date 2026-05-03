<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

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
    ): JsonResponse {
        $perPage = 10;
        $appliedFilters = [];
        $filters = $request->validated();

        $data = $repository->filters($filters, $perPage, $appliedFilters);

        $projects = $data['projects'];
        $appliedFilters = $data['appliedFilters'];
        $projectsPayload = ProjectResource::collection($projects)->response()->getData(true);

        if ($projects->isEmpty()) {
            return response()->json([
                'message' => 'Sorry no result found',
                'projects' => $projectsPayload,
                'appliedFilters' => $appliedFilters,
            ]);

        }

        return response()->json([
            'projects' => $projectsPayload,
            'appliedFilters' => $appliedFilters,
        ]);
    }

    public function bulkDelete(
        ProjectBulkDeleteRequest $request,
        BulkDeleteProjectsAction $bulkDeleteProjectsAction,
    ): JsonResponse {
        $projectIds = $request->validated('project_ids');

        $bulkDeleteProjectsAction->handle($projectIds);

        return $this->respondWithMessage('Projects deleted Successfully');
    }
}
