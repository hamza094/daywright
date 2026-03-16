<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Subscription;

use App\Enums\PlanLimitType;
use App\Enums\SubscriptionPlan;
use App\Enums\TaskStatus;
use App\Exceptions\Subscription\PlanLimitExceededException;
use App\Models\Project;
use App\Models\User;
use InvalidArgumentException;
use Laravel\Paddle\Subscription as PaddleSubscription;

/**
 * Resolves the effective subscription plan and enforces plan-based usage limits.
 */
final readonly class PlanLimitService
{
    // Public API
    public function plan(User $user): SubscriptionPlan
    {
        return SubscriptionPlan::fromUser($this->loadBillingRelations($user));
    }

    public function isDowngradedToFree(User $user): bool
    {
        $user = $this->loadBillingRelations($user);

        if ($this->plan($user) !== SubscriptionPlan::Free) {
            return false;
        }

        return $this->currentSubscription($user) !== null
            || $user->hasExpiredTrial()
            || $user->hasExpiredTrial($user->subscriptionName());
    }

    /**
     * @return array<string, array{used: int|null, max: int|null}>
     */
    public function usage(User $user, ?Project $project = null): array
    {
        $plan = $this->plan($user);
        $usage = [];

        foreach (PlanLimitType::cases() as $type) {
            $usage[$type->value] = [
                'used' => $type->requiresProject() && $project === null
                    ? null
                    : $this->countUsage($type, $user, $project),
                'max' => $plan->maxFor($type),
            ];
        }

        return $usage;
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
     * after a trial expired or after a paid plan fell back to free.
     */
    private function resolveLimitReason(User $user): string
    {
        $user = $this->loadBillingRelations($user);

        if ($this->plan($user) === SubscriptionPlan::Free) {
            if ($this->currentSubscription($user) === null
                && ($user->hasExpiredTrial() || $user->hasExpiredTrial($user->subscriptionName()))) {
                return PlanLimitExceededException::REASON_TRIAL_EXPIRED;
            }

            if ($this->isDowngradedToFree($user)) {
                return PlanLimitExceededException::REASON_DOWNGRADED_LIMIT_REACHED;
            }
        }

        return PlanLimitExceededException::REASON_LIMIT_REACHED;
    }

    private function countUsage(PlanLimitType $type, User $user, ?Project $project): int
    {
        return match ($type) {
            PlanLimitType::Projects => $user->projects()->count(),
            PlanLimitType::ActiveTasksPerProject => $this->projectFor($type, $project)->tasks()
                ->whereIn('status_id', TaskStatus::active())
                ->count(),
            PlanLimitType::MembersPerProject => $this->projectFor($type, $project)->activeMembers()->count(),
            PlanLimitType::CreatedMeetings => $user->meetings()->count(),
            PlanLimitType::ApiTokens => $user->tokens()->count(),
        };
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

    private function currentSubscription(User $user): ?PaddleSubscription
    {
        $subscription = $user->getSubscription();

        return $subscription instanceof PaddleSubscription ? $subscription : null;
    }
}
