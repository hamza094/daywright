<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Task\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Services\Api\V1\Task\TaskFeatureService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class RestoreTaskController extends ApiController
{
    public function __invoke(Project $project, Task $task, TaskFeatureService $service): JsonResponse
    {
        $service->unarchiveTask($task);
        $task->loadMissing('project:id,slug');

        return response()->json([
            'message' => 'Project task restored successfully',
            'task' => new TaskResource($task),
        ], Response::HTTP_OK);
    }
}
