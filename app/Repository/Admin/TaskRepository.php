<?php

declare(strict_types=1);

namespace App\Repository\Admin;

use App\Http\Requests\Api\V1\Admin\TaskFilterRequest;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

class TaskRepository
{
    /**
     * @return LengthAwarePaginator<int, Task>
     */
    public function getTasksWithFilter(TaskFilterRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(
            Task::query()
                ->with('project', 'status', 'assignee', 'owner')
                ->withTrashed(),
            $request,
        )
            ->allowedFilters(...TaskFilterRequest::allowedFilters())
            ->allowedSorts(...TaskFilterRequest::allowedSorts())
            ->defaultSort(...TaskFilterRequest::defaultSorts())
            ->paginate($request->perPage());
    }
}
