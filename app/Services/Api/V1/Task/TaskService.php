<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Task;

use App\Actions\NotificationAction;
use App\Actions\Task\ResetTaskNotificationAction;
use App\Http\Resources\Api\V1\TasksResource;
use App\Models\Project;
use App\Models\Task;
use App\Notifications\ProjectTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(
        private readonly ResetTaskNotificationAction $resetTaskNotificationAction,
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
                'tasksData' => TasksResource::collection($results),
            ];
        }

        // Active tasks (paginated) - use config value for page size
        $perPage = (int) config('tasks.limit', 3);
        $tasks = $query->paginate($perPage);

        return [
            'message' => $tasks->isEmpty()
              ? 'Sorry, no tasks found.'
              : $this->getMessage(false),
            'tasksData' => TasksResource::collection($tasks)->response()->getData(true),
        ];
    }

    public function checkValidation($request, $task): void
    {
        if (! $request->validated()) {
            throw ValidationException::withMessages([
                'task' => ['Field missing in task'],
            ]);
        }
    }

    public function sendNotification(Project $project): void
    {
        $notifier = auth()->user()->getNotifierData();

        NotificationAction::send(
            new ProjectTask(
                $project->name,
                $project->path(),
                $notifier
            ), $project);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateTask(Task $task, array $validated): void
    {
        $payload = $this->resetTaskNotificationAction->apply($task, $validated);

        $task->update($payload);
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
