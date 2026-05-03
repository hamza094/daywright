<?php

declare(strict_types=1);

namespace App\Repository\Admin;

use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TaskRepository
{
    /**
     * @return LengthAwarePaginator<int, Task>
     */
    public function getTasksWithFilter(array $filters, int $perPage): LengthAwarePaginator
    {
        $state = is_string($filters['state'] ?? null) ? mb_strtolower($filters['state']) : null;
        $search = is_string($filters['search'] ?? null) ? $filters['search'] : null;

        return Task::with('project', 'status', 'assignee', 'owner')
            ->withTrashed()
            ->orderByDesc('id')
            ->when($state === 'active', fn ($query) => $query->whereNull('deleted_at'))
            ->when($state === 'trashed', fn ($query) => $query->whereNotNull('deleted_at'))
            ->when($search, function ($query) use ($search): void {
                $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $search);

                $query->whereHas('project', function ($subQuery) use ($escaped): void {
                    $subQuery->where('name', 'like', '%'.$escaped.'%');
                });
            })
            ->paginate($perPage);
    }
}
