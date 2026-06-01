<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Models\Task;

final class UnassignTaskMemberAction
{
    public function execute(Task $task, int $memberId): Task
    {
        $task->assignee()->detach($memberId);

        $task->load('assignee');

        return $task;
    }
}
