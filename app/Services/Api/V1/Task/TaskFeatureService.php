<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Task;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response;

class TaskFeatureService
{
    public function assignMembers(Task $task, array $members, Project $project): void
    {
        DB::transaction(function () use ($task, $members, $project): void {
            $task->assignee()->attach($members);
            $this->notifyAssignees($members, $task, $project);
        });

        $task->load('assignee');
    }

    public function archiveTask(Task $task): void
    {
        DB::transaction(function () use ($task): void {
            $task->delete();
        });
    }

    public function unarchiveTask(Task $task): void
    {
        if (! $task->trashed()) {
            abort(403, 'Task must be trashed to perform this action');
        }

        DB::transaction(function () use ($task): void {
            $task->restore();
            $task->activities()->update(['is_hidden' => false]);
        });
    }

    public function unassignMember(Task $task, int $memberId): User
    {
        return DB::transaction(function () use ($task, $memberId): User {
            $task->assignee()->detach($memberId);

            return User::query()->findOrFail($memberId);
        });
    }

    public function removeTask(Task $task): void
    {
        abort_if(! $task->trashed(), Response::HTTP_FORBIDDEN, 'Task must be trashed to perform this action');

        $task->forceDelete();
    }

    private function notifyAssignees(array $members, Task $task, Project $project): void
    {
        $usersToNotify = User::whereIn('id', $members)
            ->where('id', '!=', Auth::id())
            ->select('id', 'name', 'email')
            ->get();

        Notification::send($usersToNotify,
            new TaskAssigned(
                $task->title,
                $project->name,
                $project->path(),
                auth()->user()->getNotifierData()
            ));
    }
}
