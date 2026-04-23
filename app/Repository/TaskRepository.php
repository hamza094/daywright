<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TaskRepository
{
    public function searchMembers(Request $request, Project $project, Task $task): Collection
    {
        $searchTerm = (string) $request->string('search')->trim();

        return $project->activeMembers()
            ->select('users.id', 'name', 'username')
            ->whereAny(['name', 'username'], 'LIKE', '%'.$searchTerm.'%')
        // ->where('users.id', '!=', auth()->id())
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
