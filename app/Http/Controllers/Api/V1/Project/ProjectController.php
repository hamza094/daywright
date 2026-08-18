<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Project\DashboardProjectRequest;
use App\Http\Requests\Api\V1\Project\ProjectStoreRequest;
use App\Http\Requests\Api\V1\Project\ProjectUpdateRequest;
use App\Http\Resources\Api\V1\Project\ProjectCollectionResource;
use App\Http\Resources\Api\V1\Project\ProjectResource;
use App\Models\Project;
use App\Services\Dashboard\UserProjectListingService;
use App\Services\Project\ProjectService;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ProjectController extends ApiController
{
    public function __construct(private readonly ProjectService $projectService) {}

    /**
     * List the authenticated user's projects.
     *
     * Returns the released project index with supported filters, sorting, and pagination.
     */
    #[Endpoint(operationId: 'projects.list')]
    public function index(
        DashboardProjectRequest $request,
        UserProjectListingService $userProjectListingService,
    ): JsonResponse {
        $paginatedProjects = $userProjectListingService->paginateUserProjects(
            $this->authenticatedUser(),
            $request->filters(),
            $request->sort(),
            $request->perPage(),
            $request->pageNumber(),
            $request->url(),
        );

        return ProjectCollectionResource::collection($paginatedProjects)->response();
    }

    /**
     * Create a new project.
     *
     * This endpoint allows authenticated users to create a new project. The request must include
     * the project's basic details, such as the name, about information, stage, and optional notes and tasks.
     * The response will include the newly created project's information along with related resources.
     */
    #[Endpoint(operationId: 'projects.create')]
    public function store(ProjectStoreRequest $request): JsonResponse
    {
        $project = $this->projectService->createProject($this->authenticatedUser(), $request->toDto());

        return $this->respondCreated(
            new ProjectResource($project, $this->projectService->projectLimits($project, $request->user()))
        );
    }

    /**
     * Retrieve a specific project.
     *
     * Returns detailed information about a project including its members, conversations, and activities.
     */
    #[Endpoint(operationId: 'projects.show')]
    public function show(Project $project): JsonResponse
    {
        $this->authorize('access', $project);

        $project = $this->projectService->loadForDetails($project);

        return $this->respondWithData(
            new ProjectResource($project, $this->projectService->projectLimits($project, $this->authenticatedUser()))
        );
    }

    /**
     * Update Project Fields
     *
     * This endpoint allows you to update the details of an existing project.
     * It requires the project's slug and the updated fields (name, about, notes) when they are present
     * in the request body and returns the updated resource.
     */
    #[Endpoint(operationId: 'projects.update')]
    public function update(Project $project, ProjectUpdateRequest $request): JsonResponse
    {
        $this->authorize('access', $project);

        $data = $request->toDto();

        if ($data->isEmpty()) {
            abort(Response::HTTP_BAD_REQUEST, "You haven't changed anything.");
        }

        $project = $this->projectService->updateProject($project, $data, $this->authenticatedUser());

        return $this->respondUpdated(
            new ProjectResource($project, $this->projectService->projectLimits($project, $request->user()))
        );
    }

    /**
     * Soft-delete (abandon) a project.
     *
     * Marks the project as abandoned using soft-deletes (deleted_at). The project can be restored later
     * or permanently deleted. This is a reversible operation.
     */
    #[Endpoint(operationId: 'projects.destroy')]
    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('manage', $project);
        $this->projectService->deleteProject($project);

        return $this->respondWithMessage($project->name.' abandoned successfully');
    }
}
