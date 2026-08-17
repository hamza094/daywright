<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Task\TaskIndexRequest;
use App\Http\Requests\Api\V1\Task\TaskRequest;
use App\Http\Requests\Api\V1\Task\TaskUpdateRequest;
use App\Http\Resources\Api\V1\Task\TaskCollectionResource;
use App\Http\Resources\Api\V1\Task\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Services\Task\TaskService;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class TaskController extends ApiController
{
    /**
     * Retrieve Project Tasks.
     *
     * This endpoint fetches a paginated task list for a specific project.
     *
     * Use `filter[state]=archived` to page through archived tasks on the same route.
     *
     * @response AnonymousResourceCollection<LengthAwarePaginator<TaskCollectionResource>>
     */
    #[Endpoint(operationId: 'tasks.list')]
    public function index(Project $project, TaskIndexRequest $request, TaskService $taskService): JsonResponse
    {
        $tasksData = $taskService->getTasksData(
            $project,
            $request->isArchived(),
            $request->perPage(),
            $request->pageNumber(),
        );

        return TaskCollectionResource::collection($tasksData)->response();
    }

    /**
     * Create a New Task.
     *
     * This endpoint allows creating a new task related to a specific project.
     */
    #[Endpoint(operationId: 'tasks.create')]
    public function store(Project $project, TaskRequest $request, TaskService $taskService): JsonResponse
    {
        $task = $taskService->createTask($project, $this->authenticatedUser(), $request->toDto());

        return $this->respondCreated(new TaskResource($task));

    }

    /** Show Task Details
     *
     * This endpoint retrieves detailed information about a specific task within a project.
     */
    #[Endpoint(operationId: 'tasks.show')]
    public function show(Project $project, Task $task): TaskResource
    {
        $task->loadMissing(['project:id,slug', 'status', 'assignee']);

        return new TaskResource($task);
    }

    /**
     * Update a Task
     *
     * This endpoint allows you to update the details of a specific task associated with a given project.
     * The user must have proper authorization to access and modify the task.
     */
    #[Endpoint(operationId: 'tasks.update')]
    public function update(Project $project, Task $task, TaskUpdateRequest $request, TaskService $taskService): JsonResponse
    {
        $this->authorize('manage', $task);

        $task = $taskService->updateTask($task, $request->toDto());

        return $this->respondUpdated(new TaskResource($task));
    }

    /**
     * Delete a task.
     *
     * Permanently removes a task that the authenticated user is allowed to manage.
     */
    #[Endpoint(operationId: 'tasks.destroy')]
    public function destroy(Project $project, Task $task, TaskService $taskService): JsonResponse
    {
        $this->authorize('manage', $task);

        $taskService->removeTask($task);

        return $this->respondWithMessage('Task deleted successfully.');
    }
}
