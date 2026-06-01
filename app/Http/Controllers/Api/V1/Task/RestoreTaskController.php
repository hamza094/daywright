<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Models\Task;
use App\Services\Task\TaskService;
use Illuminate\Http\JsonResponse;

final class RestoreTaskController extends ApiController
{
    /**
     * Restore an archived task.
     *
     * Returns an archived task to the active task list.
     */
    public function __invoke(Project $project, Task $task, TaskService $service): JsonResponse
    {
        $service->unarchiveTask($task);

        return $this->respondWithMessage('Project task restored successfully');
    }
}
