<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Actions\Project\ForceDeleteAbandonedProjectAction;
use App\Actions\Project\SendProjectUpdatedNotificationAction;
use App\Enums\Subscription\PlanLimitType;
use App\Models\Project;
use App\Models\User;
use App\Services\Api\V1\Subscription\PlanLimitService;
use App\Services\Api\V1\Subscription\SubscriptionUsageService;
use Illuminate\Support\Arr;

class ProjectService
{
    public function __construct(
        private readonly SubscriptionUsageService $subscriptionUsageService,
        private readonly PlanLimitService $planLimitService,
        private readonly SendProjectUpdatedNotificationAction $sendProjectUpdatedNotificationAction,
        private readonly ForceDeleteAbandonedProjectAction $forceDeleteAbandonedProjectAction,
    ) {}

    /**
     * @param  array{name: string, about: string, stage_id: int, notes?: string, tasks?: array<int, array<string, mixed>>}  $attributes
     */
    public function createProject(User $user, array $attributes): Project
    {
        /** @var array<int, array<string, mixed>> $tasks */
        $tasks = Arr::pull($attributes, 'tasks', []);

        return $this->planLimitService->executeWithinAccountLimit(
            PlanLimitType::Projects,
            $user,
            function (User $lockedUser) use ($attributes, $tasks): Project {
                $project = $lockedUser->projects()->create($attributes);

                if ($tasks !== []) {
                    $this->addTasksToProject($project, $lockedUser, $tasks);
                }

                return $project;
            }
        );
    }

    /**
     * @return array<int, array{key: string, label: string, scope: string, limit: array{used: int|null, max: int|null}}>|null
     */
    public function projectLimits(Project $project, User $user): ?array
    {
        $project->loadMissing('user');

        if (! $user->is($project->user)) {
            return null;
        }

        return $this->subscriptionUsageService->projectUsage($project->user, $project);
    }

    /**
     * @param  array<int, array<string, mixed>>  $tasks
     */
    public function addTasksToProject(Project $project, User $user, array $tasks): void
    {
        $tasksWithUser = collect($tasks)->map(fn (array $task): array => [...$task, 'user_id' => $user->id]);

        $project->addTasks($tasksWithUser->toArray());
    }

    public function sendNotification(Project $project, User $actor): void
    {
        $this->sendProjectUpdatedNotificationAction->execute($project, $actor);
    }

    public function forceDeleteIfAbandoned(Project $project): bool
    {
        return $this->forceDeleteAbandonedProjectAction->execute($project);
    }
}
