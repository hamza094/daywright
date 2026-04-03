<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Subscription;

use App\Actions\Subscription\BuildPlanLimitExceededExceptionAction;
use App\Actions\Subscription\ResolveUsageCountAction;
use App\Enums\Subscription\PlanLimitType;
use App\Enums\Subscription\SubscriptionPlan;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Enforces subscription plan limits, using row locks when a guarded write must stay race-safe.
 */
final readonly class PlanLimitService
{
    private const int TRANSACTION_RETRY_ATTEMPTS = 5;

    public function __construct(
        private readonly BuildPlanLimitExceededExceptionAction $buildPlanLimitExceededExceptionAction,
        private readonly ResolveUsageCountAction $resolveUsageCountAction,
    ) {}

    /**
     * Resolves the caller's effective application plan from its billing state.
     */
    public function plan(User $user): SubscriptionPlan
    {
        return SubscriptionPlan::fromUser($this->loadBillingRelations($user));
    }

    /**
     * @template TReturn
     *
     * @param  Closure(User): TReturn  $callback
     * @return TReturn
     */
    public function executeWithinAccountLimit(PlanLimitType $type, User $user, Closure $callback): mixed
    {
        $this->ensureAccountLimitType($type);

        return DB::transaction(function () use ($type, $user, $callback): mixed {
            $lockedUser = $this->lockUser($user);

            $lockedUser->loadCount([
                'projects',
                'meetings',
                'tokens',
            ]);

            $this->assertWithinLimit($type, $lockedUser);

            return $callback($lockedUser);
        }, self::TRANSACTION_RETRY_ATTEMPTS);
    }

    /**
     * @template TReturn
     *
     * @param  Closure(Project): TReturn  $callback
     * @return TReturn
     */
    public function executeWithinProjectLimit(PlanLimitType $type, Project $project, Closure $callback): mixed
    {
        $this->ensureProjectLimitType($type);

        return DB::transaction(function () use ($type, $project, $callback): mixed {
            $lockedProject = $this->lockProject($project);
            $projectOwner = $lockedProject->user;

            $lockedProject->loadCount([
                'tasks as active_tasks_count' => fn (Builder $query): Builder => $query->whereIn('status_id', TaskStatus::active()),
                'activeMembers as active_members_count' => fn (Builder $query): Builder => $query,
            ]);

            $this->assertWithinLimit($type, $projectOwner, $lockedProject);

            return $callback($lockedProject);
        }, self::TRANSACTION_RETRY_ATTEMPTS);
    }

    /**
     * Throws when the current usage has already reached the configured maximum for the plan.
     */
    public function assertWithinLimit(PlanLimitType $type, User $user, ?Project $project = null): void
    {
        $this->assertLimit(
            user: $user,
            type: $type,
            currentUsage: $this->resolveUsageCountAction->execute($type, $user, $project),
            maxAllowed: $this->plan($user)->maxFor($type),
        );
    }

    private function loadBillingRelations(User $user): User
    {
        return $user->loadMissing('subscriptions', 'customer');
    }

    private function assertLimit(
        User $user,
        PlanLimitType $type,
        int $currentUsage,
        ?int $maxAllowed,
    ): void {
        if ($this->withinLimit($currentUsage, $maxAllowed)) {
            return;
        }

        throw $this->buildPlanLimitExceededExceptionAction->execute(
            user: $user,
            type: $type,
            currentUsage: $currentUsage,
            maxAllowed: $maxAllowed,
        );
    }

    /**
     * A null limit means the feature is unbounded for the current plan.
     */
    private function withinLimit(int $usage, ?int $maxAllowed): bool
    {
        return $maxAllowed === null || $usage < $maxAllowed;
    }

    private function ensureAccountLimitType(PlanLimitType $type): void
    {
        if (! $type->requiresProject()) {
            return;
        }

        throw new InvalidArgumentException("The {$type->value} limit is not account scoped.");
    }

    private function ensureProjectLimitType(PlanLimitType $type): void
    {
        if ($type->requiresProject()) {
            return;
        }

        throw new InvalidArgumentException("The {$type->value} limit is not project scoped.");
    }

    private function lockUser(User $user): User
    {
        /** @var User $lockedUser */
        $lockedUser = User::query()
            ->whereKey($user->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedUser;
    }

    private function lockProject(Project $project): Project
    {
        /** @var Project $lockedProject */
        $lockedProject = Project::query()
            ->whereKey($project->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedProject;
    }
}
