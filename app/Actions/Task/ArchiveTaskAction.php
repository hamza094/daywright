<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Models\Task;
use Illuminate\Support\Facades\DB;

final class ArchiveTaskAction
{
    public function execute(Task $task): void
    {
        DB::transaction(function () use ($task): void {
            $task->delete();
        });
    }
}
