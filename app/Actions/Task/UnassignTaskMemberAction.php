<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UnassignTaskMemberAction
{
    public function execute(Task $task, int $memberId): User
    {
        return DB::transaction(function () use ($task, $memberId): User {
            $task->assignee()->detach($memberId);

            return User::query()->findOrFail($memberId);
        });
    }
}
