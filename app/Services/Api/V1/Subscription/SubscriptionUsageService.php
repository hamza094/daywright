<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Subscription;

use App\Actions\Subscription\ResolveUsageCountAction;
use App\Enums\Subscription\PlanLimitType;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds normalized usage payloads for account- and project-scoped limits.
 */
final readonly class SubscriptionUsageService
{
    public function __construct(
        private readonly PlanLimitService $planLimitService,
        private readonly ResolveUsageCountAction $resolveUsageCountAction
    ) {}

    /**
     * @return array<string, array{used: int|null, max: int|null}>
     */
    public function accountUsage(User $user): array
    {
        $user->loadCount([
            'projects',
            'meetings',
            'tokens',
        ]);

        return $this->buildUsage(PlanLimitType::accountTypes(), $user);
    }

    /**
     * @return array<string, array{used: int|null, max: int|null}>
     */
    public function projectUsage(User $user, Project $project): array
    {
        $project->loadCount([
            'tasks as active_tasks_count' => fn (Builder $query): Builder => $query->whereIn('status_id', TaskStatus::active()),
            'activeMembers as active_members_count' => fn (Builder $query): Builder => $query,
        ]);

        return $this->buildUsage(PlanLimitType::projectTypes(), $user, $project);
    }

    /**
     * @param  array<int, PlanLimitType>  $types
     * @return array<string, array{used: int|null, max: int|null}>
     */
    private function buildUsage(array $types, User $user, ?Project $project = null): array
    {
        $plan = $this->planLimitService->plan($user);

        return collect($types)
            ->mapWithKeys(fn (PlanLimitType $type): array => [
                $type->value => [
                    'used' => $this->resolveUsageCountAction->execute($type, $user, $project),
                    'max' => $plan->maxFor($type),
                ],
            ])
            ->toArray();
    }
}
