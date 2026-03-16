<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PlanLimitType;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\TaskRequest;
use App\Http\Requests\Api\V1\TaskUpdate;
use App\Http\Resources\Api\V1\TaskResource;
use App\Http\Resources\Api\V1\TasksResource;
use App\Models\Project;
use App\Models\Task;
use App\Services\Api\V1\Subscription\PlanLimitService;
use App\Services\Api\V1\Task\TaskService;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class TaskController extends ApiController
{
    /**
     * Retrieve Project Tasks.
     *
     * This endpoint fetches all tasks related to a specific project.
     *
     *  - Archived tasks are returned without pagination.
     * - Active tasks are paginated for easier navigation.
     *
     * @response AnonymousResourceCollection<LengthAwarePaginator<TasksResource>>
     */
    public function index(Project $project, Request $request, TaskService $taskService): JsonResponse
    {
        $validated = $request->validate([
            'request' => 'nullable|in:archived',
        ]);

        $isArchived = ($validated['request'] ?? null) === 'archived';

        $tasksData = $taskService->getTasksData($project, $isArchived);

        return response()->json($tasksData, 200);
    }

    /**
     * Create a New Task.
     *
     * This endpoint allows creating a new task related to a specific project.
     */
    public function store(Project $project, TaskRequest $request, TaskService $taskService, PlanLimitService $planLimitService): JsonResponse
    {
        $planLimitService->assertWithinLimit(PlanLimitType::ActiveTasksPerProject, Auth::user(), $project);

        $task = $project->tasks()->firstOrCreate(
            $request->validated() + ['user_id' => Auth::id(),
            ]);

        $taskService->sendNotification($project);

        $task->load('status');

        return response()->json([
            'message' => 'Task added Successfully',
            'task' => new TasksResource($task),
        ], 201);

    }

    /** Show Task Details
     *
     * This endpoint retrieves detailed information about a specific task within a project.
     */
    public function show(Project $project, Task $task): TaskResource
    {
        $task->load(['status', 'assignee']);

        return new TaskResource($task);
    }

    /**
     * Update a Task
     *
     * This endpoint allows you to update the details of a specific task associated with a given project.
     * The user must have proper authorization to access and modify the task.
     */
    public function update(Project $project, Task $task, TaskUpdate $request, TaskService $taskService)
    {
        $taskService->checkValidation($request, $task);

        $taskService->updateTask($task, $request->validated());

        if ($request->safe()->has('status_id')) {
            $task->load('status');
        }

        return response()->json([
            'message' => 'Task Updated Successfully',
            'task' => new TaskResource($task),
        ], 200);
    }
}
