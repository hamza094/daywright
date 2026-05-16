<?php

declare(strict_types=1);

namespace App\Repository\Admin;

use App\DataTransferObjects\Task\AdminTaskFilters;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TaskRepository
{
    /**
     * @return LengthAwarePaginator<int, Task>
     */
    public function getTasksWithFilter(AdminTaskFilters $filters, int $perPage): LengthAwarePaginator
    {
        return Task::with('project', 'status', 'assignee', 'owner')
            ->withTrashed()
            ->orderByDesc('id')
            ->when($filters->state === 'active', fn ($query) => $query->whereNull('deleted_at'))
            ->when($filters->state === 'trashed', fn ($query) => $query->whereNotNull('deleted_at'))
            ->when($filters->search, function ($query) use ($filters): void {
                $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $filters->search);

                $query->whereHas('project', function ($subQuery) use ($escaped): void {
                    $subQuery->where('name', 'like', '%'.$escaped.'%');
                });
            })
            ->paginate($perPage);
    }
}
