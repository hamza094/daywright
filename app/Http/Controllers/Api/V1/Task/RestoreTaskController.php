<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Models\Task;
use App\Services\Api\V1\Task\TaskFeatureService;
use Illuminate\Http\JsonResponse;

final class RestoreTaskController extends ApiController
{
    public function __invoke(Project $project, Task $task, TaskFeatureService $service): JsonResponse
    {
        $service->unarchiveTask($task);

        return $this->respondWithMessage('Project task restored successfully');
    }
}
