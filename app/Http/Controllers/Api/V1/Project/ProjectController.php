<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\DashboardProjectRequest;
use App\Http\Requests\Api\V1\ProjectStoreRequest;
use App\Http\Requests\Api\V1\ProjectUpdateRequest;
use App\Http\Resources\Api\V1\Project\ProjectCollectionResource;
use App\Http\Resources\Api\V1\Project\ProjectResource;
use App\Models\Project;
use App\Services\Api\V1\DashboardService;
use App\Services\Api\V1\PaginationService;
use App\Services\Api\V1\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProjectController extends ApiController
{
    public function __construct(private readonly ProjectService $projectService) {}

    public function index(
        DashboardProjectRequest $request,
        DashboardService $dashboardService,
    ): JsonResponse {
        $projects = $dashboardService->getUserProjects(
            $this->authenticatedUser(),
            $request->filters(),
            $request->sort(),
        );
        $perPage = $request->perPage();
        $page = (int) $request->validated('page', 1);

        $paginatedProjects = new PaginationService(
            $projects?->forPage($page, $perPage)->values() ?? collect(),
            $projects?->count() ?? 0,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ],
        );

        $projectsPayload = ProjectCollectionResource::collection($paginatedProjects)->response()->getData(true);

        return response()->json([
            'projects' => $projectsPayload,
            'projectsCount' => $projects->count(),
            'message' => $projects->isEmpty() ? 'No projects found.' : '',
        ], Response::HTTP_OK);
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
        $project = $this->projectService->createProject($this->authenticatedUser(), $request->validated());
        $project->load(['user', 'stage', 'activeMembers', 'limitedActivities']);

        return response()->json([
            'message' => 'Project created successfully.',
            'project' => new ProjectResource($project, $this->projectService->projectLimits($project, $request->user())),
        ], Response::HTTP_CREATED);
    }

    /** Retrieve a specific project
     *
     *
     * Returns detailed information about a project including its members, conversations, and activities
     */
    public function show(Project $project, Request $request): ProjectResource
    {
        $this->authorize('access', $project);

        $project->load(['user', 'stage', 'meetings', 'activeMembers', 'limitedActivities']);

        return new ProjectResource($project, $this->projectService->projectLimits($project, $request->user()));
    }

    /**
     *  Update Project Fields
     *
     *  This endpoint allows you to update the details of an existing project.
     * It requires the project's slug and the updated fields (name, about, notes) when they are present in the request body and returns the updated resource.
     *
     * @response array{message: 'Project updated successfully.',project:array{id:1, slug:'the-dimension', name:'The Dimension', about:'This is the project dimension description', score:5, created_at:'5 days ago', updated_at:'few seconds ago',links:array{self:'api/v1/projects/the-dimension'}}}
     */
    public function update(Project $project, ProjectUpdateRequest $request): JsonResponse
    {
        $this->authorize('access', $project);

        if (empty($request->validated())) {
            return response()->json([
                'error' => "You haven't changed anything.",
            ], Response::HTTP_BAD_REQUEST);
        }

        $project->update($request->validated());
        $project->load(['user', 'stage', 'activeMembers', 'limitedActivities']);

        $this->projectService->sendNotification($project, $this->authenticatedUser());

        return response()->json([
            'message' => 'Project updated successfully.',
            'project' => new ProjectResource($project, $this->projectService->projectLimits($project, $request->user())),
        ], Response::HTTP_OK);
    }

    /*
     * Forget the specified resource from database.
     *
     * @param  int  $project
     */
    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('manage', $project);
        $project->delete();

        return $this->respondWithMessage($project->name.' abandoned successfully');
    }

    public function restore(Project $project): JsonResponse
    {
        $project->restore();

        return $this->respondWithMessage($project->name.' restored successfully');

    }
}
