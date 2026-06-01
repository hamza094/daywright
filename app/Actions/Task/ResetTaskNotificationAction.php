<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\DataTransferObjects\Task\TaskUpdateData;
use App\Models\Task;

final class ResetTaskNotificationAction
{
    /**
     * Apply notification reset rules to the validated payload.
     */
    public function execute(Task $task, TaskUpdateData $data): TaskUpdateData
    {
        if (! $this->shouldResetNotification($task, $data)) {
            return $data;
        }

        return $data->withNotificationReset();
    }

    private function shouldResetNotification(Task $task, TaskUpdateData $data): bool
    {
        return $this->dueDateChanged($task, $data)
            || $this->notifyRuleChanged($task, $data);
    }

    private function dueDateChanged(Task $task, TaskUpdateData $data): bool
    {
        if (! $data->hasDueAt()) {
            return false;
        }

        $oldDue = $task->due_at?->toIso8601String();

        return (string) $data->dueAt() !== $oldDue;
    }

    private function notifyRuleChanged(Task $task, TaskUpdateData $data): bool
    {
        if (! $data->hasNotified()) {
            return false;
        }

        return (string) $data->notified() !== (string) $task->notified;
    }
}
