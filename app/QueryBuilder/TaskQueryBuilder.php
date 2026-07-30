<?php

declare(strict_types=1);

namespace App\QueryBuilder;

use App\Enums\TaskSystemStatus;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * @extends Builder<\App\Models\Task>
 *
 * @method $this onlyTrashed()
 */
class TaskQueryBuilder extends Builder
{
    public function sortBy(string $sortBy = '-created_at'): self
    {
        return match ($sortBy) {
            'created_at' => $this->orderBy('created_at', 'asc'),
            '-created_at' => $this->orderBy('created_at', 'desc'),
            'title' => $this->orderBy('title', 'asc'),
            '-title' => $this->orderBy('title', 'desc'),
            'due_at' => $this->orderBy('due_at', 'asc'),
            '-due_at' => $this->orderBy('due_at', 'desc'),
            default => $this->orderBy('created_at', 'desc'),
        };
    }

    /**
     * Filter completed tasks
     */
    public function completed(): self
    {
        return $this->where('status_id', TaskSystemStatus::Completed->value);
    }

    /**
     * Filter remaining tasks (not completed and either no due date or due in future)
     */
    public function remaining(): self
    {
        return $this->where('status_id', '!=', TaskSystemStatus::Completed->value)
            ->where(function ($q): void {
                $q->whereNull('due_at')
                    ->orWhere('due_at', '>=', now());
            });
    }

    /**
     * Filter overdue tasks (past due date and not completed)
     */
    public function overdue(): self
    {
        return $this->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->where('status_id', '!=', TaskSystemStatus::Completed->value);
    }

    /**
     * Filter tasks due soon (within specified hours, default 48)
     */
    public function dueSoon(int $hours = 48): self
    {
        return $this->whereNotNull('due_at')
            ->where('due_at', '>', now())
            ->where('due_at', '<=', now()->addHours($hours))
            ->where('status_id', '!=', TaskSystemStatus::Completed->value);
    }

    public function active(): self
    {
        return $this->whereNull('deleted_at')->with('status');
    }

    /**
     * Filter tasks due for notifications
     * Includes a 24-hour window to catch tasks that became due during scheduler downtime
     */
    public function dueForNotifications(): self
    {
        return $this->whereNotNull(['notified', 'due_at'])
            ->where('due_at', '>=', now()->subHours(24))
            ->where('notify_sent', false);
    }

    /**
     * Filter archived tasks
     */
    public function archived(): self
    {
        return $this->onlyTrashed()->with('status');
    }

    /**
     * Filter tasks assigned to the given user through the task_user pivot.
     */
    public function assignedToUser(int $userId): self
    {
        return $this->whereExists($this->assignedToUserExistsConstraint($userId));
    }

    /**
     * Filter tasks the user either owns directly or is assigned to through the pivot.
     */
    public function ownedOrAssignedToUser(int $userId): self
    {
        return $this->whereIn('tasks.id', $this->ownedOrAssignedTaskIdsSubquery($userId));
    }

    /**
     * @return Closure(QueryBuilder): mixed
     */
    private function assignedToUserExistsConstraint(int $userId): Closure
    {
        return fn (QueryBuilder $query) => $query
            ->select(DB::raw(1))
            ->from('task_user')
            ->whereColumn('task_user.task_id', 'tasks.id')
            ->where('task_user.user_id', $userId);
    }

    /**
     * Build a materialized subquery of task IDs the user owns or is assigned to.
     */
    private function ownedOrAssignedTaskIdsSubquery(int $userId): QueryBuilder
    {
        return DB::query()
            ->select('task_id')
            ->fromSub($this->userTaskIdsUnionSubquery($userId), 'user_task_ids');
    }

    /**
     * Union the owned task IDs with the assigned task IDs for the user.
     */
    private function userTaskIdsUnionSubquery(int $userId): QueryBuilder
    {
        return DB::table('tasks')
            ->select('id as task_id')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->union($this->assignedTaskIdsSubquery($userId));
    }

    /**
     * Select task IDs assigned to the user from the task_user pivot table.
     */
    private function assignedTaskIdsSubquery(int $userId): QueryBuilder
    {
        return DB::table('task_user')
            ->select('task_id')
            ->where('user_id', $userId);
    }
}
