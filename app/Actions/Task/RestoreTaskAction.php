<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\Subscription\PlanLimitType;
use App\Exceptions\TaskNotTrashedException;
use App\Models\Project;
use App\Models\Task;
use App\Services\Subscription\PlanLimitService;
use Illuminate\Support\Facades\DB;

final class RestoreTaskAction
{
    public function __construct(
        private readonly PlanLimitService $planLimitService,
    ) {}

    public function execute(Task $task): void
    {
        if (! $task->trashed()) {
            throw new TaskNotTrashedException;
        }

        $this->planLimitService->executeWithinProjectLimit(
            PlanLimitType::ActiveTasksPerProject,
            $task->project,
            fn (Project $lockedProject) => $this->performRestore($task)
        );
    }

    private function performRestore(Task $task): void
    {
        DB::transaction(function () use ($task): void {
            $task->restore();
            $task->activities()->update(['is_hidden' => false]);
        });
    }
}
