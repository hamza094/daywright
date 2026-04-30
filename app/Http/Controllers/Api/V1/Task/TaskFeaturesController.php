<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\TaskMemberSearchRequest;
use App\Http\Requests\Api\V1\TaskMembersRequest;
use App\Http\Requests\Api\V1\TaskMemberUnassignRequest;
use App\Http\Resources\Api\V1\Task\TaskMemberResource;
use App\Http\Resources\Api\V1\Task\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Repository\TaskRepository;
use App\Services\Api\V1\Task\TaskFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

class TaskFeaturesController extends ApiController
{
    /** Assign Task to Project Member(s)
     *
     * This endpoint allows assigning a task to one or more members of the project.
     *
     * ### Authorization:
     * - Ensures the task belongs to a project
     * - Access is restricted to:
     *   - The task owner.
     *   - The project owner
     * */
    public function assign(Project $project, Task $task, TaskMembersRequest $request, TaskFeatureService $service): JsonResponse
    {
        $members = $request->validated(['members']);

        $service->assignMembers($task, $members, $project);

        return response()->json([
            'message' => 'Task assigned successfully.',
            'taskMembers' => TaskMemberResource::collection($task->assignee),
        ], 200);
    }

    /** Unassign Member from Project Task
     *
     * This endpoint allows the removal of an assigned user from a specific task within a project.
     *
     * ### Authorization:
     * - Ensures the task belongs to a project
     * - Access is restricted to:
     *   - The task owner.
     *   - The project owner
     * */
    public function unassign(Project $project, Task $task, TaskMemberUnassignRequest $request, TaskFeatureService $service): JsonResponse
    {
        $memberId = (int) $request->validated('member');

        $user = $service->unassignMember($task, $memberId);

        return response()->json([
            'message' => 'Task member unassigned.',
            'member' => new TaskMemberResource($user),
        ], 200);
    }

    /**
     * Archive Project Task
     *
     * This endpoint allows authorized users to archive a specific task within a project.
     *
     * Archiving a task marks it as no longer active but retains its data for reference purposes.
     *
     * ### Authorization:
     * - Ensures the task belongs to a project
     * - Access is restricted to:
     *   - The task assigned members
     *   - The task owner.
     *   - The project owner
     * */
    public function archive(Project $project, Task $task, TaskFeatureService $service): JsonResponse
    {
        $service->archiveTask($task);
        $task->loadMissing('project:id,slug');

        return response()->json([
            'message' => 'Project task archived successfully',
            'task' => new TaskResource($task),
        ], Response::HTTP_OK);
    }

    /**
     * Unarchive Project Task
     *
     * This endpoint allows authorized users to unarchive a specific task within a project.
     *
     * Unarchiving a task marks it as active, allowing task actions to be performed.
     *
     * ### Authorization:
     * - Ensures the task belongs to a project
     * - Access is restricted to:
     *   - The task assigned members
     *   - The task owner.
     *   - The project owner.
     * */
    public function unarchive(Project $project, Task $task, TaskFeatureService $service): JsonResponse
    {
        $service->unarchiveTask($task);
        $task->loadMissing('project:id,slug');

        return response()->json([
            'message' => 'Project task restored successfully',
            'task' => new TaskResource($task),
        ], Response::HTTP_OK);
    }

    /** Search Members
     *
     * Search through project active members
     *
     * ### Authorization:
     * - Ensures the task belongs to a project
     * - Access is restricted to:
     *    - The task assigned members
     *   - The task owner.
     *   - The project owner
     * */
    public function search(Project $project, Task $task, TaskMemberSearchRequest $request, TaskRepository $repository): AnonymousResourceCollection
    {
        $searchResults = $repository->searchMembers($request, $project, $task);

        return TaskMemberResource::collection($searchResults);
    }

    /**
     * Delete a Task
     *
     * This endpoint allows you to delete a specific  task associated with a project.
     *
     ** **Authorization:**
     * - The user must have appropriate permissions to access and delete the task.
     *
     *  **Functionality:**
     * - Deletes all associated activities of the task.
     * - Permanently removes the task from the database (force delete).
     */
    public function remove(Project $project, Task $task, TaskFeatureService $service): HttpResponse
    {
        $service->removeTask($task);

        return response()->noContent();
    }
}
