<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Models\Task;
use Illuminate\Support\Facades\DB;

final class UnassignTaskMemberAction
{
    public function execute(Task $task, int $memberId): Task
    {
        DB::transaction(function () use ($task, $memberId): void {
            $task->assignee()->detach($memberId);
        });

        $task->load('assignee');

        return $task;
    }
}
