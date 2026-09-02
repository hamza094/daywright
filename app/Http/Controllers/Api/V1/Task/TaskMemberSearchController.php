<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Task\TaskMemberSearchRequest;
use App\Http\Resources\Api\V1\Task\TaskMemberResource;
use App\Models\Project;
use App\Models\Task;
use App\Repository\TaskRepository;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TaskMemberSearchController extends ApiController
{
    /**
     * Search assignable task members.
     *
     * Returns project members that match the provided search term for assignment workflows.
     */
    #[Endpoint(operationId: 'tasks.searchMembers')]
    public function __invoke(Project $project, Task $task, TaskMemberSearchRequest $request, TaskRepository $repository): AnonymousResourceCollection
    {
        return TaskMemberResource::collection($repository->searchMembers($request->searchTerm(), $project, $task));
    }
}
