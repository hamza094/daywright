<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Models\Task;
use App\Services\Task\TaskService;
use Illuminate\Http\JsonResponse;

final class ArchiveTaskController extends ApiController
{
    /**
     * Archive a task.
     *
     * Moves the task into the archived state without deleting its history.
     */
    public function __invoke(Project $project, Task $task, TaskService $service): JsonResponse
    {
        $service->archiveTask($task);

        return $this->respondWithMessage('Project task archived successfully');
    }
}
