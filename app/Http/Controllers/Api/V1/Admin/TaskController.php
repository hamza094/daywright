<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Task\BulkDeleteTasksAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Admin\TaskBulkDeleteRequest;
use App\Http\Requests\Api\V1\Admin\TaskFilterRequest;
use App\Http\Resources\Api\V1\Admin\TaskResource;
use App\Repository\Admin\TaskRepository;
use Illuminate\Http\JsonResponse;

class TaskController extends ApiController
{
    public function index(
        TaskRepository $taskRepository,
        TaskFilterRequest $request,
    ): JsonResponse {
        $tasks = $taskRepository->getTasksWithFilter($request);

        return TaskResource::collection($tasks)->response();
    }

    public function bulkDelete(TaskBulkDeleteRequest $request, BulkDeleteTasksAction $bulkDeleteTasksAction): JsonResponse
    {
        $data = $request->toDto();

        $bulkDeleteTasksAction->execute($data->taskIds);

        return $this->respondWithMessage('Tasks deleted successfully.');
    }
}
