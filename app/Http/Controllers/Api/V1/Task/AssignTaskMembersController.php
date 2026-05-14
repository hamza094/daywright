<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\TaskMembersRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\Task\TaskFeatureService;
use Illuminate\Http\JsonResponse;

final class AssignTaskMembersController extends ApiController
{
    /**
     * Assign members to a task.
     *
     * Assigns one or more project members to the specified task.
     */
    public function __invoke(Project $project, Task $task, TaskMembersRequest $request, TaskFeatureService $service): JsonResponse
    {
        $members = $request->validated('members');

        $service->assignMembers($task, $members, $project, $this->authenticatedUser());

        return $this->respondWithMessage('Task assigned successfully.');
    }
}
