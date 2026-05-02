<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Models\Task;
use Symfony\Component\HttpFoundation\Response;

final class DeleteTaskAction
{
    public function execute(Task $task): void
    {
        abort_if(! $task->trashed(), Response::HTTP_FORBIDDEN, 'Task must be trashed to perform this action');

        $task->forceDelete();
    }
}
