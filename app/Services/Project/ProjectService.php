<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Actions\Project\ForceDeleteAbandonedProjectAction;
use App\Actions\Project\SendProjectUpdatedNotificationAction;
use App\DataTransferObjects\Project\ProjectCreateData;
use App\DataTransferObjects\Project\ProjectStageUpdateData;
use App\DataTransferObjects\Project\ProjectUpdateData;
use App\Enums\StageStatus;
use App\Enums\Subscription\PlanLimitType;
use App\Models\Project;
use App\Models\User;
use App\Services\Subscription\PlanLimitService;
use App\Services\Subscription\SubscriptionUsageService;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    private const array PROJECT_RESOURCE_RELATIONS = ['user', 'stage', 'activeMembers', 'limitedActivities'];

    public function __construct(
        private readonly SubscriptionUsageService $subscriptionUsageService,
        private readonly PlanLimitService $planLimitService,
        private readonly SendProjectUpdatedNotificationAction $sendProjectUpdatedNotificationAction,
        private readonly ForceDeleteAbandonedProjectAction $forceDeleteAbandonedProjectAction,
    ) {}

    public function createProject(User $user, ProjectCreateData $data): Project
    {
        $attributes = $data->projectAttributes();
        $tasks = $data->starterTasks();

        $project = $this->planLimitService->executeWithinAccountLimit(
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

        return $this->loadForResponse($project);
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

    public function loadForResponse(Project $project): Project
    {
        $project->loadMissing(self::PROJECT_RESOURCE_RELATIONS);

        return $project;
    }

    public function loadForDetails(Project $project): Project
    {
        return $this->loadForResponse($project);
    }

    public function updateProject(Project $project, ProjectUpdateData $data, User $actor): Project
    {
        $project->update($data->attributes());

        $this->sendNotification($project, $actor);

        return $this->loadForResponse($project);
    }

    public function deleteProject(Project $project): void
    {
        $project->delete();
    }

    public function restoreProject(Project $project): void
    {
        $project->restore();
    }

    public function updateStageStatus(Project $project, ProjectStageUpdateData $data): Project
    {
        return DB::transaction(function () use ($project, $data): Project {
            // Handle stage transition using state machine
            $newStage = $data->stage();
            $project->transitionTo($newStage, 'stage_id');

            // Update other stage-related fields
            $project->update([
                'postponed_reason' => $this->getPostponedReason($project, $data),
                'stage_updated_at' => now(),
            ]);

            $project->load('stage');

            return $project;
        });
    }

    public function sendNotification(Project $project, User $actor): void
    {
        $this->sendProjectUpdatedNotificationAction->execute($project, $actor);
    }

    public function forceDeleteIfAbandoned(Project $project): bool
    {
        return $this->forceDeleteAbandonedProjectAction->execute($project);
    }

    private function getPostponedReason(Project $project, ProjectStageUpdateData $data): ?string
    {
        return ($project->stage->name === StageStatus::Postponed->value && ! in_array($data->postponedReason, [null, '', '0'], true))
            ? $data->postponedReason
            : null;
    }
}
