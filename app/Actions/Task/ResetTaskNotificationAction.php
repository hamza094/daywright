<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Models\Task;

final class ResetTaskNotificationAction
{
    public function apply(Task $task, array $validated): array
    {
        if (! $this->shouldResetNotification($task, $validated)) {
            return $validated;
        }

        $validated['notify_sent'] = false;

        return $validated;
    }

    private function shouldResetNotification(Task $task, array $validated): bool
    {
        return $this->dueDateChanged($task, $validated)
            || $this->notifyRuleChanged($task, $validated);
    }

    private function dueDateChanged(Task $task, array $validated): bool
    {
        if (! isset($validated['due_at'])) {
            return false;
        }

        $oldDue = $task->due_at?->format('Y-m-d H:i:s');

        return (string) $validated['due_at'] !== $oldDue;
    }

    private function notifyRuleChanged(Task $task, array $validated): bool
    {
        if (! array_key_exists('notified', $validated)) {
            return false;
        }

        return (string) $validated['notified'] !== (string) $task->notified;
    }
}
