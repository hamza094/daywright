<?php

declare(strict_types=1);

namespace App\Repository;

use App\Http\Requests\Api\V1\UserTasksRequest;
use App\Models\Task;
use App\QueryBuilder\TaskQueryBuilder;
use Illuminate\Pagination\CursorPaginator;

class UserTasksDataRepository
{
    private const int PER_PAGE = 50;

    /**
     * @return CursorPaginator<Task>
     */
    public function getTasks(int $userId, UserTasksRequest $request): CursorPaginator
    {
        $validated = $request->validated();

        $query = Task::query();

        // Apply user context filters with explicit logic
        $this->applyUserContextFilters($query, $userId, $validated);

        return $query
            ->select('id', 'title', 'user_id', 'project_id', 'status_id', 'due_at', 'created_at')
            ->when($validated['completed'] ?? false, fn ($q) => $q->completed())
            ->when($validated['overdue'] ?? false, fn ($q) => $q->overdue())
            ->when($validated['remaining'] ?? false, fn ($q) => $q->remaining())
            ->with([
                'project' => fn ($query) => $query
                    ->withTrashed()
                    ->select('id', 'name', 'slug'),
                'status',
                'assignee' => fn ($query) => $query->select('users.id', 'users.uuid', 'users.name'),
            ])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->cursorPaginate(self::PER_PAGE, ['*'], 'cursor', $request->query('cursor'));
    }

    protected function applyUserContextFilters(TaskQueryBuilder $query, int $userId, array $validated): void
    {
        $created = $validated['user_created'] ?? false;
        $assigned = $validated['task_assigned'] ?? false;

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
