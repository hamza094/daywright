<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class AssignTaskMembersAction
{
    /**
     * @param  array<int, int|string>  $members
     */
    public function execute(Task $task, Project $project, User $actor, array $members): void
    {
        DB::transaction(function () use ($task, $project, $actor, $members): void {
            $task->assignee()->attach($members);

            $usersToNotify = User::query()
                ->whereIn('id', $members)
                ->where('id', '!=', $actor->id)
                ->select('id', 'name', 'email')
                ->get();

            Notification::send($usersToNotify, new TaskAssigned(
                $task->title,
                $project->name,
                $project->path(),
                $actor->getNotifierData()
            ));
        });

        $task->load('assignee');
    }
}
