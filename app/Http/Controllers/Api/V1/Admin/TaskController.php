<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\BuildPaginatedPayloadAction;
use App\Actions\Task\BulkDeleteTasksAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Admin\TaskBulkDeleteRequest;
use App\Http\Requests\Api\V1\Admin\TaskFilterRequest;
use App\Http\Resources\Api\V1\Admin\TaskResource;
use App\Repository\Admin\TaskRepository;
use F9Web\ApiResponseHelpers;
use Illuminate\Http\JsonResponse;

class TaskController extends ApiController
{
    use ApiResponseHelpers;

    public function index(
        TaskRepository $taskRepository,
        TaskFilterRequest $request,
        BuildPaginatedPayloadAction $buildPaginatedPayloadAction,
    ): JsonResponse {
        $perPage = 50;

        $tasks = $taskRepository->getTasksWithFilter($request, $perPage);

        $tasksPayload = $buildPaginatedPayloadAction->handle($tasks, TaskResource::class);

        if ($tasks->isEmpty()) {
            return response()->json(array_merge(['message' => 'Sorry no related tasks found'], $tasksPayload));
        }

        return response()->json($tasksPayload);
    }

    public function bulkDelete(TaskBulkDeleteRequest $request, BulkDeleteTasksAction $bulkDeleteTasksAction): JsonResponse
    {
        $taskIds = $request->validated('task_ids');

        $bulkDeleteTasksAction->handle($taskIds);

        return $this->respondWithSuccess([
            'message' => 'Tasks deleted Successfully',
        ]);
    }
}
