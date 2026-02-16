<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\ProjectStoreRequest;
use App\Http\Requests\Api\V1\ProjectUpdateRequest;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Http\Resources\Api\V1\ProjectsResource;
use App\Models\Project;
use App\Services\Api\V1\ProjectService;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProjectController extends ApiController
{
    // ApiResponseHelpers trait removed - using explicit responses

    /**
     * Create a new project.
     *
     * This endpoint allows authenticated users to create a new project. The request must include
    the project's basic details, such as the name, about information, stage, and optional notes and tasks.
    The response will include the newly created project's information along with related resources.
     */
    public function store(ProjectStoreRequest $request, ProjectService $service): JsonResponse
    {
        DB::beginTransaction();

        try {

            $project = Auth::user()->projects()
                ->create($request->safe()->except(['tasks']));

            if ($request->tasks) {
                $service->addTasksToProject($project, $request->safe()->only(['tasks']));
            }

            DB::commit();

        } catch (Exception $ex) {

            DB::rollBack();

            throw $ex;
        }

        return response()->json([
            'message' => 'Project Created Successfully',
            'project' => new ProjectsResource($project),
        ], 201);
    }

    /** Retrieve a specific project
     *
     *
     * Returns detailed information about a project including its members, conversations, and activities
     */
    public function show(Project $project): ProjectResource
    {
        $this->authorize('access', $project);

        $project->load(['stage', 'meetings', 'activeMembers', 'limitedActivities']);

        return new ProjectResource($project);
    }

    /**
     *  Update Project Fields
     *
     *  This endpoint allows you to update the details of an existing project.
     * It requires the project's slug and the updated fields (name, about, notes) when they are present in the request body and returns the updated resource.
     *
     * @response array{message: 'Project Updated Successfully',project:array{id:1, slug:'the-dimension', name:'The Dimension', about:'This is the project dimension description', score:5, created_at:'5 days ago', updated_at:'few seconds ago',links:array{self:'api/v1/projects/the-dimension'}}}
     */
    public function update(Project $project, ProjectUpdateRequest $request, ProjectService $service): JsonResponse
    {
        $this->authorize('access', $project);

        if (empty($request->validated())) {
            return response()->json([
                'error' => "You haven't changed anything.",
            ], 400);
        }

        $project->update($request->validated());

        $service->sendNotification($project);

        return response()->json([
            'message' => 'Project Updated Successfully',
            'project' => new ProjectResource($project),
        ], 200);
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

        return response()->json([
            'message' => $project->name.' abandoned successfully',
        ], 200);
    }

    public function restore(Project $project): JsonResponse
    {
        $project->restore();

        return response()->json([
            'message' => $project->name.' restored successfully',
        ], 200);
    }

    public function delete(Project $project, ProjectService $service): JsonResponse
    {
        $deleted = $service->forceDeleteIfAbandoned($project);

        if (! $deleted) {
            return response()->json(['message' => 'Only abandoned projects can be deleted permanently.'], 403);
        }

        return response()->json([
            'message' => 'Project deleted successfully',
        ], 200);
    }
}
