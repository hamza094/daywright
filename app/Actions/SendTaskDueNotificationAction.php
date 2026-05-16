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

    public function execute(Task $task): bool
    {
        $taskForNotification = DB::transaction(function () use ($task): ?Task {
            $lockedTask = $this->lockTask($task);
            $lockedTask->loadMissing(['assignee', 'project', 'owner']);

            if (! $this->canNotify($lockedTask)) {
                return null;
            }

            $lockedTask->notify_sent = true;
            $lockedTask->saveQuietly();

            return $lockedTask;
        }, attempts: self::TRANSACTION_RETRY_ATTEMPTS);

        if ($taskForNotification === null) {
            return false;
        }

        $project = $taskForNotification->project;

        if ($project === null) {
            return false;
        }

        foreach ($taskForNotification->assignee as $user) {
            $user->notify(
                new TaskDue(
                    $taskForNotification->due_at,
                    $taskForNotification->title,
                    $taskForNotification->notified,
                    NotificationActorData::fromUser($taskForNotification->owner),
                    $project->name,
                    $project->slug
                ));
        }

        return true;
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
        /** @var Task $lockedTask */
        $lockedTask = Task::query()
            ->whereKey($task->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedTask;
    }
}
