<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\Subscription\PlanLimitType;
use App\Exceptions\TaskNotTrashedException;
use App\Models\Task;
use App\Services\Subscription\PlanLimitService;

final readonly class RestoreTaskAction
{
    public function __construct(
        private PlanLimitService $planLimitService,
    ) {}

    public function execute(Task $task): void
    {
        if (! $task->trashed()) {
            throw new TaskNotTrashedException;
        }

        $this->planLimitService->executeWithinProjectLimit(
            PlanLimitType::TasksPerProject,
            $task->project,
            fn () => $this->performRestore($task)
        );
    }

    private function performRestore(Task $task): void
    {
        $task->restore();
        $task->activities()->update(['is_hidden' => false]);
    }
}
