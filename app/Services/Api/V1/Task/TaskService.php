<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Task;

use App\Actions\NotificationAction;
use App\Actions\Task\ResetTaskNotificationAction;
use App\Enums\Subscription\PlanLimitType;
use App\Http\Resources\Api\V1\Task\TaskCollectionResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ProjectTask;
use App\Services\Api\V1\Subscription\PlanLimitService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(
        private readonly ResetTaskNotificationAction $resetTaskNotificationAction,
        private readonly PlanLimitService $planLimitService,
    ) {}

    public function getTasksData(Project $project, bool $isArchived): array
    {
        $query = $this->getTasks($project, $isArchived);

        // Early return for archived tasks (no pagination)
        if ($isArchived) {
            $results = $query->get();

            return [
                'message' => $results->isEmpty()
                  ? 'Sorry, no tasks found.'
                  : $this->getMessage(true),
                'tasksData' => TaskCollectionResource::collection($results),
            ];
        }

        // Active tasks (paginated) - use config value for page size
        $perPage = (int) config('tasks.limit', 3);

        return [
            'message' => $query->get()->isEmpty()
              ? 'Sorry, no tasks found.'
              : $this->getMessage(false),
            'tasksData' => TaskCollectionResource::collection($query->get())->paginate($perPage),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createTask(Project $project, User $user, array $validated): Task
    {
        /** @var Task $task */
        $task = $this->planLimitService->executeWithinProjectLimit(
            PlanLimitType::ActiveTasksPerProject,
            $project,
            fn (Project $lockedProject): Task => $lockedProject->tasks()->firstOrCreate(
                $validated + ['user_id' => $user->id]
            )
        );

        $task->loadMissing('project:id,slug');
        $task->load('status');

        $this->sendNotification($project, $user);

        return $task;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateTask(Task $task, array $validated): Task
    {
        if ($validated === []) {
            throw ValidationException::withMessages([
                'task' => ['Field missing in task'],
            ]);
        }

        $payload = $this->resetTaskNotificationAction->apply($task, $validated);

        $task->update($payload);
        $task->loadMissing('project:id,slug');

        if (array_key_exists('status_id', $validated)) {
            $task->load('status');
        }

        return $task;
    }

    private function sendNotification(Project $project, User $actor): void
    {
        $notifier = $actor->getNotifierData();

        NotificationAction::send(
            new ProjectTask(
                $project->name,
                $project->path(),
                $notifier
            ), $project);
    }

    private function getTasks(Project $project, bool $isArchived): HasMany
    {
        return $project->tasks()
            ->with('project:id,slug')
            ->when(
                $isArchived,
                fn (Builder $query) => $query->archived(),
                fn (Builder $query) => $query->active()
            );
    }

    private function getMessage(bool $isArchived): string
    {
        return 'Project '.($isArchived ? 'Archived' : 'Active').' Tasks';
    }
}
