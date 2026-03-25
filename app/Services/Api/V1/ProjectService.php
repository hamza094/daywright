<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Actions\NotificationAction;
use App\Actions\Project\CancelProjectZoomMeetingsAction;
use App\Jobs\CancelZoomMeetingsJob;
use App\Models\Project;
use App\Notifications\ProjectUpdated;
use App\Services\Api\V1\Subscription\PlanLimitService;

class ProjectService
{
    public function __construct(private readonly PlanLimitService $planLimitService) {}

    /**
     * @return array<string, array{used: int|null, max: int|null}>|null
     */
    public function projectLimits(Project $project): ?array
    {
        if (! $project->relationLoaded('user')) {
            return null;
        }

        $user = auth()->user();

        if (! $user->is($project->user)) {
            return null;
        }

        return $this->planLimitService->projectUsage($project->user, $project);
    }

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
