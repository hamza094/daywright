<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Enums\Meeting\MeetingSyncStatus;
use App\Enums\Subscription\PlanLimitType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class PlanUsageCountResolver
{
    public function resolve(PlanLimitType $type, User $user, ?Project $project = null): int
    {
        if (! $type->requiresProject()) {
            return $this->accountCount($type, $user);
        }

        $project = $this->projectFor($type, $project);

        return $this->projectCount($type, $project);
    }

    private function accountCount(PlanLimitType $type, User $user): int
    {
        $loadedCount = $this->loadedCount($user, $type->loadedCountAttribute());

        if ($loadedCount !== null) {
            return $loadedCount;
        }

        return match ($type) {
            PlanLimitType::Projects => $user->projects()->count(),
            PlanLimitType::CreatedMeetings => $user->meetings()->where('sync_status', MeetingSyncStatus::Active)->count(),
            PlanLimitType::ApiTokens => $user->tokens()->count(),
            default => throw new InvalidArgumentException("Invalid account limit type: {$type->value}"),
        };
    }

    private function projectCount(PlanLimitType $type, Project $project): int
    {
        $loadedCount = $this->loadedCount($project, $type->loadedCountAttribute());

        if ($loadedCount !== null) {
            return $loadedCount;
        }

        return match ($type) {
            PlanLimitType::TasksPerProject => $project->tasks()->count(),
            PlanLimitType::MembersPerProject => $project->activeMembers()->count(),
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
