<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Actions\NotificationAction;
use App\Actions\Project\CancelProjectZoomMeetingsAction;
use App\Jobs\CancelZoomMeetingsJob;
use App\Models\Project;
use App\Notifications\ProjectUpdated;

class ProjectService
{
    public function addTasksToProject(Project $project, array $tasks): void
    {
        $tasksWithUser = collect($tasks['tasks'])->map(fn ($task) => [...$task, 'user_id' => auth()->id()]);

        $project->addTasks($tasksWithUser->toArray());
    }

    public function sendNotification(Project $project): void
    {
        if ($project->activeMembers->isEmpty()) {
            return;
        }

        $notifier = auth()->user()->getNotifierData();

        NotificationAction::send(
            new ProjectUpdated(
                $project->name,
                $project->path(),
                $notifier
            ), $project);
    }

    public function forceDeleteIfAbandoned(Project $project): bool
    {
        if (! $project->trashed()) {
            return false;
        }

        $meetings = (new CancelProjectZoomMeetingsAction)->execute($project);

        if ($meetings !== []) {
            CancelZoomMeetingsJob::dispatch($meetings);
        }

        $project->forceDelete();

        return true;
    }
}
