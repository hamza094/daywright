<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Task;

use App\Actions\NotificationAction;
use App\Http\Resources\Api\V1\TasksResource;
use App\Models\Project;
use App\Notifications\ProjectTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class TaskService
{
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

        return [
            'message' => $query->get()->isEmpty()
              ? 'Sorry, no tasks found.'
              : $this->getMessage(false),
            'tasksData' => TasksResource::collection($query->get())->paginate($perPage),
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

    public function sendNotification($project): void
    {
        $notifier = auth()->user()->getNotifierData();

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
            ->with('project')
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
