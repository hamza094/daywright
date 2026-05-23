<?php

declare(strict_types=1);

namespace App\Services\Task;

use App\Actions\NotifyProjectMembersAction;
use App\Actions\Task\ArchiveTaskAction;
use App\Actions\Task\AssignTaskMembersAction;
use App\Actions\Task\DeleteTaskAction;
use App\Actions\Task\ResetTaskNotificationAction;
use App\Actions\Task\RestoreTaskAction;
use App\Actions\Task\UnassignTaskMemberAction;
use App\DataTransferObjects\Notification\NotificationActorData;
use App\DataTransferObjects\Task\TaskCreateData;
use App\DataTransferObjects\Task\TaskUpdateData;
use App\Enums\Subscription\PlanLimitType;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ProjectTask;
use App\Services\Subscription\PlanLimitService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(
        private readonly NotifyProjectMembersAction $notifyProjectMembersAction,
        private readonly ResetTaskNotificationAction $resetTaskNotificationAction,
        private readonly PlanLimitService $planLimitService,
        private readonly AssignTaskMembersAction $assignTaskMembersAction,
        private readonly UnassignTaskMemberAction $unassignTaskMemberAction,
        private readonly ArchiveTaskAction $archiveTaskAction,
        private readonly RestoreTaskAction $restoreTaskAction,
        private readonly DeleteTaskAction $deleteTaskAction,
    ) {}

    public function getTasksData(Project $project, bool $isArchived, int $perPage, int $page): LengthAwarePaginator
    {
        $query = $this->getTasks($project, $isArchived);

        return $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();
    }

    public function createTask(Project $project, User $user, TaskCreateData $data): Task
    {
        /** @var Task $task */
        $task = $this->planLimitService->executeWithinProjectLimit(
            PlanLimitType::ActiveTasksPerProject,
            $project,
            fn (Project $lockedProject): Task => $lockedProject->tasks()->firstOrCreate(
                $data->toCreateAttributes($user->id)
            )
        );

        $task->loadMissing('project:id,slug');
        $task->load('status');

        $this->sendNotification($project, $user);

        return $task;
    }

    public function updateTask(Task $task, TaskUpdateData $data): Task
    {
        if ($data->isEmpty()) {
            throw ValidationException::withMessages([
                'task' => ['Field missing in task'],
            ]);
        }

        $payload = $this->resetTaskNotificationAction->execute($task, $data);

        $task->update($payload->toArray());
        $task->loadMissing('project:id,slug');

        if ($data->hasStatusUpdate()) {
            $task->load('status');
        }

        return $task;
    }

    /**
     * @param  array<int, int|string>  $members
     */
    public function assignMembers(Task $task, array $members, Project $project, User $actor): Task
    {
        return $this->hydrateTaskResource($this->assignTaskMembersAction->execute($task, $project, $actor, $members));
    }

    public function archiveTask(Task $task): void
    {
        $this->archiveTaskAction->execute($task);
    }

    public function unarchiveTask(Task $task): void
    {
        $this->restoreTaskAction->execute($task);
    }

    public function unassignMember(Task $task, int $memberId): Task
    {
        return $this->hydrateTaskResource($this->unassignTaskMemberAction->execute($task, $memberId));
    }

    public function removeTask(Task $task): void
    {
        $this->deleteTaskAction->execute($task);
    }

    private function hydrateTaskResource(Task $task): Task
    {
        $task->loadMissing('project:id,slug');
        $task->loadMissing('status');
        $task->load('assignee');

        return $task;
    }

    private function sendNotification(Project $project, User $actor): void
    {
        $notifier = NotificationActorData::fromUser($actor);

        $this->notifyProjectMembersAction->execute(
            new ProjectTask(
                $project->name,
                $project->slug,
                $notifier
            ), $project, $actor);
    }

    private function getTasks(Project $project, bool $isArchived): HasMany
    {
        return $project->tasks()
            ->with('project:id,slug')
            ->orderBy('id')
            ->when(
                $isArchived,
                fn (Builder $query) => $query->archived(),
                fn (Builder $query) => $query->active()
            );
    }
}
