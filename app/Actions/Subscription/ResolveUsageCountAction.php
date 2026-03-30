<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Enums\Subscription\PlanLimitType;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class ResolveUsageCountAction
{
    public function execute(PlanLimitType $type, User $user, ?Project $project = null): int
    {
        if (! $type->requiresProject()) {
            return $this->accountCount($type, $user);
        }

        $project = $this->projectFor($type, $project);

        return $this->projectCount($type, $project);
    }

    private function accountCount(PlanLimitType $type, User $user): int
    {
        return match ($type) {
            PlanLimitType::Projects => $this->loadedCount($user, 'projects_count') ?? $user->projects()->count(),
            PlanLimitType::CreatedMeetings => $this->loadedCount($user, 'meetings_count') ?? $user->meetings()->count(),
            PlanLimitType::ApiTokens => $this->loadedCount($user, 'tokens_count') ?? $user->tokens()->count(),
            default => throw new InvalidArgumentException("Invalid account limit type: {$type->value}"),
        };
    }

    private function projectCount(PlanLimitType $type, Project $project): int
    {
        return match ($type) {
            PlanLimitType::ActiveTasksPerProject => $this->loadedCount($project, 'active_tasks_count') ?? $project->tasks()->whereIn('status_id', TaskStatus::active())->count(),
            PlanLimitType::MembersPerProject => $this->loadedCount($project, 'active_members_count') ?? $project->activeMembers()->count(),
            default => throw new InvalidArgumentException("Invalid project limit type: {$type->value}"),
        };
    }

    private function loadedCount(Model $model, string $attribute): ?int
    {
        if (! array_key_exists($attribute, $model->getAttributes())) {
            return null;
        }

        return max(0, (int) $model->getAttribute($attribute));
    }

    private function projectFor(PlanLimitType $type, ?Project $project): Project
    {
        return $project ?? throw new InvalidArgumentException(
            "The {$type->value} limit requires a project context."
        );
    }
}
