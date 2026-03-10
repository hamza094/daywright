<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Project\BuildPaginatedProjectPayloadAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Admin\TaskBulkDeleteRequest;
use App\Http\Requests\Api\V1\Admin\TaskFilterRequest;
use App\Http\Resources\Api\V1\Admin\TaskResource;
use App\Models\Task;
use App\Repository\Admin\TaskRepository;
use F9Web\ApiResponseHelpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TaskController extends ApiController
{
    use ApiResponseHelpers;

    public function index(
        TaskRepository $taskRepository,
        TaskFilterRequest $request,
        BuildPaginatedProjectPayloadAction $buildPaginatedProjectPayloadAction,
    ): JsonResponse {
        $perPage = 50;

        $tasks = $taskRepository->getTasksWithFilter($request, $perPage);

        $tasksPayload = $buildPaginatedProjectPayloadAction->handle($tasks, TaskResource::class);

        if ($tasks->isEmpty()) {
            return response()->json(array_merge(['message' => 'Sorry no related tasks found'], $tasksPayload));
        }

        return response()->json($tasksPayload);
    }

    public function bulkDelete(TaskBulkDeleteRequest $request): JsonResponse
    {
        $taskIds = $request->validated('task_ids');

        DB::transaction(function () use ($taskIds): void {
            Task::withTrashed()->whereIn('id', $taskIds)->each(function ($task): void {
                $task->assignee()->detach();
                $task->forceDelete();
            });
        });

        return $this->respondWithSuccess([
            'message' => 'Tasks deleted Successfully',
        ]);

    }
}
