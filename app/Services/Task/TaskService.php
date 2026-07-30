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
use App\DataTransferObjects\Task\AssignTaskMembersData;
use App\DataTransferObjects\Task\TaskCreateData;
use App\DataTransferObjects\Task\TaskUpdateData;
use App\DataTransferObjects\Task\UnassignTaskMemberData;
use App\Enums\Subscription\PlanLimitType;
use App\Enums\TaskSystemStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ProjectTask;
use App\QueryBuilder\TaskQueryBuilder;
use App\Services\Subscription\PlanLimitService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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

    /**
     * @return LengthAwarePaginator<int, Task>
     */
    public function getTasksData(Project $project, bool $isArchived, int $perPage, int $page): LengthAwarePaginator
    {
        $query = $this->getTasks($project, $isArchived);

        return $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();
    }

    public function createTask(Project $project, User $user, TaskCreateData $data): Task
    {
        /** @var Task $task */
        $task = $this->planLimitService->executeWithinProjectLimit(
            PlanLimitType::TasksPerProject,
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

        return DB::transaction(function () use ($task, $data): Task {
            $payload = $this->resetTaskNotificationAction->execute($task, $data);

            // Handle status transition separately using state machine
            if ($data->hasStatusUpdate() && $data->statusId() !== null) {
                $newStatus = TaskSystemStatus::from($data->statusId());
                $task->transitionTo($newStatus, 'status_id');
            }

            // Update other attributes (excluding status_id)
            $nonStatusAttributes = $payload->attributesWithoutStatus();
            if ($nonStatusAttributes !== []) {
                $task->update($nonStatusAttributes);
            }

            $task->loadMissing('project:id,slug');

            if ($data->hasStatusUpdate()) {
                $task->load('status');
            }

            return $task;
        });
    }

    public function assignMembers(Task $task, AssignTaskMembersData $data, Project $project, User $actor): Task
    {
        return $this->hydrateTaskResource($this->assignTaskMembersAction->execute($task, $project, $actor, $data->members));
    }

    public function archiveTask(Task $task): void
    {
        $this->archiveTaskAction->execute($task);
    }

    public function unarchiveTask(Task $task): void
    {
        $this->restoreTaskAction->execute($task);
    }

    public function unassignMember(Task $task, UnassignTaskMemberData $data): Task
    {
        return $this->hydrateTaskResource($this->unassignTaskMemberAction->execute($task, $data->member));
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

    private function getTasks(Project $project, bool $isArchived): TaskQueryBuilder
    {
        /** @var TaskQueryBuilder $tasks */
        $tasks = $project->tasks()->getQuery();

        $tasks->with('project:id,slug')->orderBy('id');

        if ($isArchived) {
            return $tasks->archived();
        }

        return $tasks->active();
    }
}
