<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Task\TaskMembersRequest;
use App\Http\Resources\Api\V1\Task\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Services\Task\TaskService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\JsonResponse;

final class AssignTaskMembersController extends ApiController
{
    /**
     * Assign members to a task.
     *
     * Assigns one or more project members to the specified task. This triggers a TaskAssigned email notification
     * to the assigned members. Only the task owner or project owner can assign members.
     *
     * @headerParameter Idempotency-Key string required Unique key to prevent duplicate assignment requests
     */
    #[Endpoint(operationId: 'tasks.assignMembers')]
    #[HeaderParameter(name: 'Idempotency-Key', type: 'string', required: true, description: 'Unique key to prevent duplicate assignment requests')]
    public function __invoke(Project $project, Task $task, TaskMembersRequest $request, TaskService $service): JsonResponse
    {
        $task = $service->assignMembers($task, $request->toDto(), $project, $this->authenticatedUser());

        return $this->respondUpdated(new TaskResource($task));
    }
}
