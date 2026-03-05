<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Project\BuildPaginatedProjectPayloadAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Admin\ProjectBulkDeleteRequest;
use App\Http\Requests\Api\V1\Admin\ProjectFilterRequest;
use App\Http\Resources\Api\V1\Admin\ProjectResource;
use App\Models\Project;
use App\Repository\Admin\ProjectFiltersRepository;
use F9Web\ApiResponseHelpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProjectController extends ApiController
{
    use ApiResponseHelpers;

    public function index(
        ProjectFilterRequest $request,
        ProjectFiltersRepository $repository,
        BuildPaginatedProjectPayloadAction $buildPaginatedProjectPayloadAction,
    ): JsonResponse {
        $perPage = 10;
        $appliedFilters = [];
        $filters = $request->validated();

        $data = $repository->filters($filters, $perPage, $appliedFilters);

        $projects = $data['projects'];
        $appliedFilters = $data['appliedFilters'];
        $projectsPayload = $buildPaginatedProjectPayloadAction->handle($projects, ProjectResource::class);

        if ($projects->isEmpty()) {
            return $this->respondWithSuccess([
                'message' => 'Sorry no result found',
                'projects' => $projectsPayload,
                'appliedFilters' => $appliedFilters,
            ]);

        }

        return $this->respondWithSuccess([
            'projects' => $projectsPayload,
            'appliedFilters' => $appliedFilters,
        ]);
    }

    public function bulkDelete(ProjectBulkDeleteRequest $request): JsonResponse
    {
        $projectIds = $request->validated('project_ids');

        DB::transaction(function () use ($projectIds): void {
            Project::withTrashed()->whereIn('id', $projectIds)->each(function ($project): void {
                $project->forceDelete();
            });
        });

        return $this->respondWithSuccess([
            'message' => 'Projects deleted Successfully',
        ]);

    }
}
