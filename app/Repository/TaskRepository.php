<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;

class TaskRepository
{
    public function searchMembers(Project $project, Task $task, string $searchTerm): Collection
    {
        return $project->activeMembers()
            ->select('users.id', 'users.uuid', 'name', 'username', 'email')
            ->whereAny(['name', 'username'], 'LIKE', $searchTerm.'%')
            ->leftJoin('task_user', function ($join) use ($task): void {
                $join->on('users.id', '=', 'task_user.user_id')
                    ->where('task_user.task_id', '=', $task->id);
            })
            ->whereNull('task_user.task_id')
            ->orderBy('name')
            ->take(5)
            ->get();
    }
}
