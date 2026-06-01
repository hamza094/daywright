<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Exceptions\TaskNotTrashedException;
use App\Models\Task;

final class DeleteTaskAction
{
    public function execute(Task $task): void
    {
        if (! $task->trashed()) {
            throw new TaskNotTrashedException;
        }

        $task->forceDelete();
    }
}
