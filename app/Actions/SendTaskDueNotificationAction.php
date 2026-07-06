<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\Notification\NotificationActorData;
use App\Enums\TaskDueNotifies;
use App\Models\Task;
use App\Notifications\TaskDue;
use Illuminate\Support\Facades\DB;

final class SendTaskDueNotificationAction
{
    private const int TRANSACTION_RETRY_ATTEMPTS = 5;

    /**
     * The notify_sent field indicates that a notification has been queued for delivery.
     * It is set to true AFTER the notification is dispatched to the queue.
     * This ensures that if notification dispatch fails, notify_sent is not set and the task can be retried.
     * The transaction ensures atomicity: if dispatch fails, the flag is not committed.
     */
    public function execute(Task $task): bool
    {
        $project = $task->project;

        if ($project === null) {
            return false;
        }

        $taskForNotification = DB::transaction(function () use ($task): ?Task {
            $lockedTask = $this->lockTask($task);
            $lockedTask->loadMissing(['assignee', 'project', 'owner']);

            if (! $this->canNotify($lockedTask)) {
                return null;
            }

            foreach ($lockedTask->assignee as $user) {
                $user->notify(
                    new TaskDue(
                        dueDate: $lockedTask->due_at,
                        taskTitle: $lockedTask->title,
                        notifiedOption: $lockedTask->notified,
                        notifierData: NotificationActorData::fromUser($lockedTask->owner),
                        projectName: $lockedTask->project->name,
                        projectSlug: $lockedTask->project->slug
                    )
                );
            }

            $lockedTask->notify_sent = true;
            $lockedTask->saveQuietly();

            return $lockedTask;
        }, attempts: self::TRANSACTION_RETRY_ATTEMPTS);

        return $taskForNotification !== null;
    }

    private function canNotify(Task $task): bool
    {
        $project = $task->project;

        if ($project === null || $project->trashed() || $task->notify_sent) {
            return false;
        }

        $minutes = TaskDueNotifies::getPeriodInMinutes($task->notified);

        if ($minutes === null || $task->due_at === null) {
            return false;
        }

        $notificationTime = $task->due_at->copy()->subMinutes($minutes);

        return now()->greaterThanOrEqualTo($notificationTime);
    }

    private function lockTask(Task $task): Task
    {
        return Task::query()
            ->whereKey($task->getKey())
            ->lockForUpdate()
            ->firstOrFail();

    }
}
