<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Task\TaskMemberUnassignRequest;
use App\Http\Resources\Api\V1\Task\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Services\Task\TaskService;
use Illuminate\Http\JsonResponse;

final class UnassignTaskMemberController extends ApiController
{
    /**
     * Unassign a member from a task.
     *
     * Removes one assigned project member from the specified task.
     */
    public function __invoke(Project $project, Task $task, TaskMemberUnassignRequest $request, TaskService $service): JsonResponse
    {
        $task = $service->unassignMember($task, $request->toDto());

        return $this->respondUpdated(new TaskResource($task));
    }
}
