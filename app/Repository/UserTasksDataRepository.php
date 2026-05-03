<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UserTasksDataRepository
{
    /**
     * @param  array{user_created: bool, task_assigned: bool, completed: bool, overdue: bool, remaining: bool}  $filters
     */
    public function getTasks(int $userId, array $filters): Collection
    {
        $query = Task::query();

        // Apply user context filters with explicit logic
        $this->applyUserContextFilters($query, $userId, $filters);

        return $query
            ->when($filters['completed'] ?? false, fn ($q) => $q->completed())
            ->when($filters['overdue'] ?? false, fn ($q) => $q->overdue())
            ->when($filters['remaining'] ?? false, fn ($q) => $q->remaining())
            ->with([
                'project' => fn ($q) => $q->withTrashed(),
                'status',
                'assignee',
            ])
            ->get();
    }

    /**
     * @param  array{user_created: bool, task_assigned: bool, completed: bool, overdue: bool, remaining: bool}  $filters
     * @return array<int, string>
     */
    public function appliedFilters(array $filters): array
    {
        $labels = [
            'user_created' => 'Filter by Created',
            'task_assigned' => 'Filter by Assigned',
            'completed' => 'Filter by Completed',
            'overdue' => 'Filter by Overdue',
            'remaining' => 'Filter by Remaining',
        ];

        $enabled = collect($filters)
            ->filter()
            ->keys();

        return collect($labels)->only($enabled)->values()->all();
    }

    /**
     * @param  array{user_created: bool, task_assigned: bool, completed: bool, overdue: bool, remaining: bool}  $filters
     */
    protected function applyUserContextFilters(Builder $query, int $userId, array $filters): void
    {
        $created = $filters['user_created'] ?? false;
        $assigned = $filters['task_assigned'] ?? false;

        if ($created && $assigned) {
            // Both filters: tasks created by user OR assigned to user
            $query->where(function ($q) use ($userId): void {
                $q->where('user_id', $userId)
                    ->orWhereHas('assignee', fn ($sub) => $sub->where('users.id', $userId));
            });
        } elseif ($created) {
            $query->where('user_id', $userId);
        } elseif ($assigned) {
            $query->whereHas('assignee', fn ($sub) => $sub->where('users.id', $userId));
        } else {
            // No explicit user context filters - default to user's tasks (created OR assigned)
            $query->where(function ($q) use ($userId): void {
                $q->where('user_id', $userId)
                    ->orWhereHas('assignee', fn ($sub) => $sub->where('users.id', $userId));
            });
        }
    }
}
