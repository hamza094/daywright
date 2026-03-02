<?php

declare(strict_types=1);

namespace App\Repository\Admin;

use App\Http\Requests\Api\V1\Admin\TaskFilterRequest;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TaskRepository
{
    /**
     * @return LengthAwarePaginator<int, Task>
     */
    public function getTasksWithFilter(TaskFilterRequest $request, int $perPage): LengthAwarePaginator
    {
        return Task::with('project', 'status', 'assignee', 'owner')
            ->withTrashed()
            ->latest('created_at')
            ->when($request->string('filter')->trim()->lower()->exactly('active'), fn ($query) => $query->whereNull('deleted_at'))
            ->when($request->string('filter')->trim()->lower()->exactly('trashed'), fn ($query) => $query->whereNotNull('deleted_at'))
            ->when($request->validated('search'), function ($query) use ($request): void {
                $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $request->validated('search'));

                $query->whereHas('project', function ($subQuery) use ($escaped): void {
                    $subQuery->where('name', 'like', '%'.$escaped.'%');
                });
            })
            ->paginate($perPage);
    }
}
