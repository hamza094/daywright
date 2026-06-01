<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Exceptions\TaskNotTrashedException;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

final class RestoreTaskAction
{
    public function execute(Task $task): void
    {
        if (! $task->trashed()) {
            throw new TaskNotTrashedException;
        }

        DB::transaction(function () use ($task): void {
            $task->restore();
            $task->activities()->update(['is_hidden' => false]);
        });
    }
}
