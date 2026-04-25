<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProjectUserSearchRequest;
use App\Http\Requests\Api\V1\TaskMembersRequest;
use App\Http\Resources\Api\V1\Task\TaskMemberResource;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repository\TaskRepository;
use App\Rules\TaskAssigneeMember;
use App\Services\Api\V1\Task\TaskFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskFeaturesController extends Controller
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
            'message' => 'Task assigned to member Successfully',
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
    public function unassign(Project $project, Task $task, Request $request): JsonResponse
    {
        $request->validate([
            /**
             * The user to be unassigned must:
             * - Exist in the `users` table.
             * - Be a valid assignee of the given task.
             */
            'member' => ['required', 'exists:users,id',
                new TaskAssigneeMember($task)],
        ]);

        $task->assignee()->detach($request->member);

        $user = User::find($request->member);

        return response()->json([
            'message' => 'Task member Unassigned',
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

        return response()->json([
            'message' => 'Project task archived successfully',
            'task' => new TaskResource($task),
        ], 200);
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

        return response()->json([
            'message' => 'Project task unArchived successfully',
            'task' => new TaskResource($task),
        ], 200);
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
    public function search(Project $project, Task $task, ProjectUserSearchRequest $request, TaskRepository $repository): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $searchResults = $repository->searchMembers($project, $task, (string) $validated['query']);

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
    public function remove(Project $project, Task $task, TaskFeatureService $service): JsonResponse
    {
        if (! $task->trashed()) {
            abort(403, 'Task must be trashed to perform this action');
        }

        $service->removeTask($task);

        return response()->json(204);
    }
}
