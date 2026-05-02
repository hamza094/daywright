<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Models\Task;
use Illuminate\Support\Facades\DB;

final class RestoreTaskAction
{
    public function execute(Task $task): void
    {
        if (! $task->trashed()) {
            abort(403, 'Task must be trashed to perform this action');
        }

        DB::transaction(function () use ($task): void {
            $task->restore();
            $task->activities()->update(['is_hidden' => false]);
        });
    }
}
