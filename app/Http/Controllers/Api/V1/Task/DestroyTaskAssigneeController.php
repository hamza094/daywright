<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\DataTransferObjects\Task\UnassignTaskMemberData;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Task\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Rules\TaskAssigneeMember;
use App\Services\Task\TaskService;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class DestroyTaskAssigneeController extends ApiController
{
    /**
     * Unassign a member from a task.
     *
     * Removes one assigned project member from the specified task.
     */
    #[Endpoint(operationId: 'tasks.assignees.destroy')]
    public function __invoke(Project $project, Task $task, int $user, TaskService $service): JsonResponse
    {
        // Validate that the user is assigned to the task
        $rule = new TaskAssigneeMember($task);
        if (! $rule->passes('user', $user)) {
            throw ValidationException::withMessages([
                'user' => $rule->message(),
            ]);
        }

        $task = $service->unassignMember($task, UnassignTaskMemberData::fromValidated(['member' => $user]));

        return $this->respondUpdated(new TaskResource($task));
    }
}
