<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Project;
use App\Models\Task;
use App\QueryBuilder\Concerns\EscapesLikeWildcards;
use Illuminate\Support\Collection;

class TaskRepository
{
    use EscapesLikeWildcards;

    public function searchMembers(string $searchTerm, Project $project, Task $task): Collection
    {
        return $project->activeMembers()
            ->select('users.id', 'users.uuid', 'name', 'username', 'email')
            ->whereAny(['name', 'username'], 'LIKE', $this->escapeLikeWildcards($searchTerm).'%')
            ->leftJoin('task_user', function ($join) use ($task): void {
                $join->on('users.id', '=', 'task_user.user_id')
                    ->where('task_user.task_id', '=', $task->id);
            })
            ->whereNull('task_user.task_id')
            ->orderBy('name')
            ->orderBy('users.id')
            ->take(5)
            ->get();
    }
}
