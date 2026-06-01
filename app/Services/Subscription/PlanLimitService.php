<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Actions\Subscription\PlanLimitExceededExceptionFactory;
use App\Actions\Subscription\PlanUsageCountResolver;
use App\Enums\Subscription\PlanLimitType;
use App\Enums\Subscription\SubscriptionPlan;
use App\Models\Project;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Enforces subscription plan limits, using row locks when a guarded write must stay race-safe.
 */
final readonly class PlanLimitService
{
    private const int TRANSACTION_RETRY_ATTEMPTS = 5;

    public function __construct(
        private PlanLimitExceededExceptionFactory $planLimitExceededExceptionFactory,
        private PlanUsageCountResolver $planUsageCountResolver,
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

            $lockedUser->loadCount(PlanLimitType::accountCountLoaders());

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

            $lockedProject->loadCount(PlanLimitType::projectCountLoaders());

            $this->assertWithinLimit($type, $lockedProject->user, $lockedProject);

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
            currentUsage: $this->planUsageCountResolver->resolve($type, $user, $project),
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

        throw $this->planLimitExceededExceptionFactory->execute(
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
        /** @var Project */
        return Project::query()
            ->whereKey($project->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
