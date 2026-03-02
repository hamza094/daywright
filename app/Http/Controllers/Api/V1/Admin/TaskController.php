<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\TaskBulkDeleteRequest;
use App\Http\Requests\Api\V1\Admin\TaskFilterRequest;
use App\Http\Resources\Api\V1\Admin\TaskResource;
use App\Models\Task;
use App\Repository\Admin\TaskRepository;
use F9Web\ApiResponseHelpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    use ApiResponseHelpers;

    public function index(TaskRepository $taskRepository, TaskFilterRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $perPage = 50;

        $tasks = $taskRepository->getTasksWithFilter($request, $perPage);

        if ($tasks->isEmpty()) {
            return $this->respondWithSuccess([
                'message' => 'Sorry no releated tasks found',
            ]);
        }

        return TaskResource::collection($tasks);
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
