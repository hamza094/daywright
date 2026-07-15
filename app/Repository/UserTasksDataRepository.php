<?php

declare(strict_types=1);

namespace App\Repository;

use App\DataTransferObjects\Task\UserTaskFilters;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;

class UserTasksDataRepository
{
    public function getTasks(int $userId, UserTaskFilters $filters, int $perPage = 15): CursorPaginator
    {
        $query = Task::query();

        // Apply user context filters with explicit logic
        $this->applyUserContextFilters($query, $userId, $filters);

        return $query
            ->select('id', 'title', 'user_id', 'project_id', 'status_id', 'due_at', 'created_at')
            ->when($filters->completed, fn ($q) => $q->completed())
            ->when($filters->overdue, fn ($q) => $q->overdue())
            ->when($filters->remaining, fn ($q) => $q->remaining())
            ->with([
                'project' => fn ($q) => $q->withTrashed(),
                'status',
                'assignee' => fn ($query) => $query->select('users.id', 'users.uuid', 'users.name'),

            ])
            ->orderBy('id')
            ->cursorPaginate($perPage);
    }

    protected function applyUserContextFilters(Builder $query, int $userId, UserTaskFilters $filters): void
    {
        $created = $filters->userCreated;
        $assigned = $filters->taskAssigned;

        if (($created && $assigned) || (! $created && ! $assigned)) {
            $query->ownedOrAssignedToUser($userId);

            return;
        }

        if ($created) {
            $query->where('user_id', $userId);

            return;
        }

        $query->assignedToUser($userId);
    }
}
