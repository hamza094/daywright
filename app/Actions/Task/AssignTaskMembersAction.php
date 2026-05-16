<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\DataTransferObjects\Notification\NotificationActorData;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class AssignTaskMembersAction
{
    private const int TRANSACTION_RETRY_ATTEMPTS = 5;

    /**
     * @param  array<int, int|string>  $members
     */
    public function execute(Task $task, Project $project, User $actor, array $members): void
    {
        DB::transaction(function () use ($task, $project, $actor, $members): void {
            $lockedTask = $this->lockTask($task);
            $changes = $lockedTask->assignee()->syncWithoutDetaching($members);

            $attachedMemberIds = $this->extractAttachedMemberIds($changes, $actor->id);

            if ($attachedMemberIds === []) {
                return;
            }

            DB::afterCommit(function () use ($attachedMemberIds, $task, $project, $actor): void {
                $usersToNotify = $this->fetchUsersForNotification($attachedMemberIds);
                $this->notifyUsers($usersToNotify, $task, $project, $actor);
            });
        }, attempts: self::TRANSACTION_RETRY_ATTEMPTS);

        $task->load('assignee');
    }

    /**
     * Extract integer IDs of newly attached members, excluding the actor.
     *
     * @param  array<string, mixed>  $changes
     * @return array<int>
     */
    private function extractAttachedMemberIds(array $changes, int $actorId): array
    {
        return collect($changes['attached'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === $actorId)
            ->values()
            ->all();
    }

    /**
     * Fetch minimal user records for notification.
     *
     * @param  array<int>  $ids
     */
    private function fetchUsersForNotification(array $ids): \Illuminate\Database\Eloquent\Collection
    {
        return User::query()
            ->whereIn('id', $ids)
            ->select('id', 'name', 'email')
            ->get();
    }

    private function notifyUsers(\Illuminate\Database\Eloquent\Collection $users, Task $task, Project $project, User $actor): void
    {
        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new TaskAssigned(
            $task->title,
            $project->name,
            $project->slug,
            NotificationActorData::fromUser($actor)
        ));
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
