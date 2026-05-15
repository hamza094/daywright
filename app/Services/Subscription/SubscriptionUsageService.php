<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Actions\Subscription\PlanUsageCountResolver;
use App\Enums\Subscription\PlanLimitType;
use App\Models\Project;
use App\Models\User;

/**
 * Builds normalized usage payloads for account- and project-scoped limits.
 */
final readonly class SubscriptionUsageService
{
    public function __construct(
        private readonly PlanLimitService $planLimitService,
        private readonly PlanUsageCountResolver $planUsageCountResolver
    ) {}

    /**
     * @return array<int, array{key: string, label: string, scope: string, limit: array{used: int|null, max: int|null}}>
     */
    public function accountUsage(User $user): array
    {
        $user->loadCount(PlanLimitType::accountCountLoaders());

        return $this->buildUsage(PlanLimitType::accountTypes(), $user);
    }

    /**
     * @return array<int, array{key: string, label: string, scope: string, limit: array{used: int|null, max: int|null}}>
     */
    public function projectUsage(User $user, Project $project): array
    {
        $project->loadCount(PlanLimitType::projectCountLoaders());

        return $this->buildUsage(PlanLimitType::projectTypes(), $user, $project);
    }

    /**
     * @param  array<int, PlanLimitType>  $types
     * @return array<int, array{key: string, label: string, scope: string, limit: array{used: int|null, max: int|null}}>
     */
    private function buildUsage(array $types, User $user, ?Project $project = null): array
    {
        $plan = $this->planLimitService->plan($user);

        return collect($types)
            ->map(fn (PlanLimitType $type): array => [
                'key' => $type->value,
                'label' => $type->displayLabel(),
                'scope' => $type->scope(),
                'limit' => [
                    'used' => $this->planUsageCountResolver->resolve($type, $user, $project),
                    'max' => $plan->maxFor($type),
                ],
            ])
            ->values()
            ->toArray();
    }
}
