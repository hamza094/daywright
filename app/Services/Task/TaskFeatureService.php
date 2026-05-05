<?php

declare(strict_types=1);

namespace App\Services\Task;

use App\Actions\Task\ArchiveTaskAction;
use App\Actions\Task\AssignTaskMembersAction;
use App\Actions\Task\DeleteTaskAction;
use App\Actions\Task\RestoreTaskAction;
use App\Actions\Task\UnassignTaskMemberAction;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskFeatureService
{
    public function __construct(
        private readonly AssignTaskMembersAction $assignTaskMembersAction,
        private readonly UnassignTaskMemberAction $unassignTaskMemberAction,
        private readonly ArchiveTaskAction $archiveTaskAction,
        private readonly RestoreTaskAction $restoreTaskAction,
        private readonly DeleteTaskAction $deleteTaskAction,
    ) {}

    /**
     * @param  array<int, int|string>  $members
     */
    public function assignMembers(Task $task, array $members, Project $project, User $actor): void
    {
        $this->assignTaskMembersAction->execute($task, $project, $actor, $members);
    }

    public function archiveTask(Task $task): void
    {
        $this->archiveTaskAction->execute($task);
    }

    public function unarchiveTask(Task $task): void
    {
        $this->restoreTaskAction->execute($task);
    }

    public function unassignMember(Task $task, int $memberId): User
    {
        return $this->unassignTaskMemberAction->execute($task, $memberId);
    }

    public function removeTask(Task $task): void
    {
        $this->deleteTaskAction->execute($task);
    }
}
