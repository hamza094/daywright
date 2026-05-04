<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\TaskMemberUnassignRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\Api\V1\Task\TaskFeatureService;
use Illuminate\Http\JsonResponse;

final class UnassignTaskMemberController extends ApiController
{
    public function __invoke(Project $project, Task $task, TaskMemberUnassignRequest $request, TaskFeatureService $service): JsonResponse
    {
        $memberId = (int) $request->validated('member');
        $service->unassignMember($task, $memberId);

        return $this->respondWithMessage('Task member unassigned.');
    }
}
