<?php

declare(strict_types=1);

namespace App\Repository;

use App\DataTransferObjects\Task\UserTaskFilters;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UserTasksDataRepository
{
    public function getTasks(int $userId, UserTaskFilters $filters): Collection
    {
        $query = Task::query();

        // Apply user context filters with explicit logic
        $this->applyUserContextFilters($query, $userId, $filters);

        return $query
            ->when($filters->completed, fn ($q) => $q->completed())
            ->when($filters->overdue, fn ($q) => $q->overdue())
            ->when($filters->remaining, fn ($q) => $q->remaining())
            ->with([
                'project' => fn ($q) => $q->withTrashed(),
                'status',
                'assignee',
            ])
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function appliedFilters(UserTaskFilters $filters): array
    {
        $labels = [
            'user_created' => 'Filter by Created',
            'task_assigned' => 'Filter by Assigned',
            'completed' => 'Filter by Completed',
            'overdue' => 'Filter by Overdue',
            'remaining' => 'Filter by Remaining',
        ];

        $enabled = collect($filters->toArray())
            ->filter()
            ->keys();

        return collect($labels)->only($enabled)->values()->all();
    }

    protected function applyUserContextFilters(Builder $query, int $userId, UserTaskFilters $filters): void
    {
        $created = $filters->userCreated;
        $assigned = $filters->taskAssigned;

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
