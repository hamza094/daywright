<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Subscription;

use App\Enums\PlanLimitType;
use App\Enums\SubscriptionPlan;
use App\Enums\TaskStatus;
use App\Exceptions\Subscription\PlanLimitExceededException;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Resolves the effective subscription plan and enforces plan-based usage limits.
 */
final class PlanLimitService
{
    /**
     * @var array<int, PlanLimitType>
     */
    private const ACCOUNT_LIMIT_TYPES = [
        PlanLimitType::Projects,
        PlanLimitType::CreatedMeetings,
        PlanLimitType::ApiTokens,
    ];

    /**
     * @var array<int, PlanLimitType>
     */
    private const PROJECT_LIMIT_TYPES = [
        PlanLimitType::ActiveTasksPerProject,
        PlanLimitType::MembersPerProject,
    ];

    /**
     * @var array<string, SubscriptionPlan>
     */
    // Caching removed: compute values on demand to simplify behavior.

    // Public API
    public function plan(User $user): SubscriptionPlan
    {
        return SubscriptionPlan::fromUser($this->loadBillingRelations($user));
    }

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

        return $this->buildUsage(self::ACCOUNT_LIMIT_TYPES, $user);
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

        return $this->buildUsage(self::PROJECT_LIMIT_TYPES, $user, $project);
    }

    /**
     * @return array<string, array{used: int|null, max: int|null}>
     */
    public function usage(User $user, ?Project $project = null): array
    {
        return [
            ...$this->accountUsage($user),
            ...($project !== null ? $this->projectUsage($user, $project) : []),
        ];
    }

    public function assertWithinLimit(PlanLimitType $type, User $user, ?Project $project = null): void
    {
        $this->assertLimit(
            user: $user,
            limitType: $type->exceptionKey(),
            currentUsage: $this->countUsage($type, $user, $project),
            maxAllowed: $this->plan($user)->maxFor($type),
            messageSubject: $type->messageSubject(),
        );
    }

    // Private helpers (in logical order)
    private function assertLimit(
        User $user,
        string $limitType,
        int $currentUsage,
        ?int $maxAllowed,
        string $messageSubject,
    ): void {
        if ($this->withinLimit($currentUsage, $maxAllowed)) {
            return;
        }

        $plan = $this->plan($user);

        throw new PlanLimitExceededException(
            message: "You have reached the maximum number of {$messageSubject} on the ".ucfirst($plan->value).' plan.',
            limitType: $limitType,
            reason: $this->resolveLimitReason($user),
            currentUsage: $currentUsage,
            maxAllowed: $maxAllowed,
        );
    }

    private function withinLimit(int $usage, ?int $maxAllowed): bool
    {
        return $maxAllowed === null || $usage < $maxAllowed;
    }

    /**
     * Preserve the existing API error semantics for free users who reached limits
     * after a trial expired.
     */
    private function resolveLimitReason(User $user): string
    {
        $user = $this->loadBillingRelations($user);

        if ($this->plan($user) === SubscriptionPlan::Free) {
            if (! $user->hasSubscriptionRecord()
                && ($user->hasExpiredTrial() || $user->hasExpiredTrial($user->subscriptionName()))) {
                return PlanLimitExceededException::REASON_TRIAL_EXPIRED;
            }
        }

        return PlanLimitExceededException::REASON_LIMIT_REACHED;
    }

    private function countUsage(PlanLimitType $type, User $user, ?Project $project): int
    {
        return $this->resolveUsageCount($type, $user, $project);
    }

    private function resolveUsageCount(PlanLimitType $type, User $user, ?Project $project): int
    {
        if (in_array($type, self::ACCOUNT_LIMIT_TYPES, true)) {
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

    /**
     * @param  array<int, PlanLimitType>  $types
     * @return array<string, array{used: int|null, max: int|null}>
     */
    private function buildUsage(array $types, User $user, ?Project $project = null): array
    {
        $plan = $this->plan($user);

        return collect($types)
            ->mapWithKeys(fn (PlanLimitType $type): array => [
                $type->value => [
                    'used' => $this->countUsage($type, $user, $project),
                    'max' => $plan->maxFor($type),
                ],
            ])
            ->toArray();
    }

    private function projectFor(PlanLimitType $type, ?Project $project): Project
    {
        return $project ?? throw new InvalidArgumentException(
            "The {$type->value} limit requires a project context."
        );
    }

    private function loadBillingRelations(User $user): User
    {
        return $user->loadMissing('subscriptions', 'customer');
    }
}
