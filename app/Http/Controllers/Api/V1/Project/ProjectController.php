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
    the project's basic details, such as the name, about information, stage, and optional notes and tasks.
    The response will include the newly created project's information along with related resources.
     */
    public function store(ProjectStoreRequest $request): JsonResponse
    {
        $project = $this->projectService->createProject($this->authenticatedUser(), $request->projectCreateData());

        return $this->respondCreated(
            new ProjectResource($project, $this->projectService->projectLimits($project, $request->user()))
        );
    }

    /** Retrieve a specific project
     *
     *
     * Returns detailed information about a project including its members, conversations, and activities
     */
    public function show(Project $project): ProjectResource
    {
        $this->authorize('access', $project);

        $project = $this->projectService->loadForDetails($project);

        return new ProjectResource($project, $this->projectService->projectLimits($project, $this->authenticatedUser()));
    }

    /**
     *  Update Project Fields
     *
     *  This endpoint allows you to update the details of an existing project.
     * It requires the project's slug and the updated fields (name, about, notes) when they are present in the request body and returns the updated resource.
     *     */
    public function update(Project $project, ProjectUpdateRequest $request): JsonResponse
    {
        $this->authorize('access', $project);

        $data = $request->projectUpdateData();

        if ($data->isEmpty()) {
            abort(Response::HTTP_BAD_REQUEST, "You haven't changed anything.");
        }

        $project = $this->projectService->updateProject($project, $data, $this->authenticatedUser());

        return $this->respondUpdated(
            new ProjectResource($project, $this->projectService->projectLimits($project, $request->user()))
        );
    }

    /**
     * Soft-delete a project.
     *
     * Marks the project as abandoned so it can be restored or permanently deleted later.
     */
    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('manage', $project);
        $this->projectService->deleteProject($project);

        return $this->respondWithMessage($project->name.' abandoned successfully');
    }
}
