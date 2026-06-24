<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Enums\Subscription\PlanLimitType;
use App\Enums\Subscription\SubscriptionPlan;
use App\Exceptions\Subscription\PlanLimitExceededException;
use App\Models\User;

final readonly class PlanLimitExceededExceptionFactory
{
    public function execute(
        User $user,
        PlanLimitType $type,
        int $currentUsage,
        ?int $maxAllowed,
    ): PlanLimitExceededException {
        $user = $this->loadBillingRelations($user);

        $plan = SubscriptionPlan::fromUser($user);

        return new PlanLimitExceededException(
            message: $this->limitExceededMessage($type, $plan, $currentUsage, $maxAllowed),
            limitType: $type->exceptionKey(),
            limitLabel: $type->displayLabel(),
            reason: $this->resolveLimitReason($user, $plan),
            currentUsage: $currentUsage,
            maxAllowed: $maxAllowed,
            limitScope: $type->requiresProject()
                ? PlanLimitExceededException::SCOPE_PROJECT
                : PlanLimitExceededException::SCOPE_ACCOUNT,
            limitOwnerId: (int) $user->getKey(),
        );
    }

    private function limitExceededMessage(
        PlanLimitType $type,
        SubscriptionPlan $plan,
        int $currentUsage,
        ?int $maxAllowed,
    ): string {
        if ($this->isOverCurrentPlanLimit($currentUsage, $maxAllowed)) {
            return $this->overCurrentPlanLimitMessage($type, $plan);
        }

        if ($type->requiresProject()) {
            if ($type === PlanLimitType::MembersPerProject) {
                $max = $maxAllowed ?? 'unlimited';

                return "This project has reached its member limit ({$currentUsage}/{$max}). "
                    .'Unable to join. Ask the project owner to upgrade the plan or remove inactive members.';
            }

            if ($type === PlanLimitType::TasksPerProject) {
                $max = $maxAllowed ?? 'unlimited';

                return "This project has reached its task limit ({$currentUsage}/{$max}). "
                    .'Unable to restore. Ask the project owner to upgrade the plan or delete existing tasks.';
            }

            return 'This project has reached the maximum number of '
                .$this->projectLimitMessageSubject($type)
                .' allowed on its current plan.';
        }

        return 'You have reached the maximum number of '
            .$type->messageSubject()
            .' on the '
            .ucfirst($plan->value)
            .' plan.';
    }

    private function overCurrentPlanLimitMessage(PlanLimitType $type, SubscriptionPlan $plan): string
    {
        if ($type->requiresProject()) {
            return 'This project is already above the maximum number of '
                .$this->projectLimitMessageSubject($type)
                .' allowed on its current plan. Reduce usage before creating more.';
        }

        return 'You are already above the maximum number of '
            .$type->messageSubject()
            .' allowed on the '
            .ucfirst($plan->value)
            .' plan. Reduce usage or upgrade your plan before creating more.';
    }

    private function projectLimitMessageSubject(PlanLimitType $type): string
    {
        return match ($type) {
            PlanLimitType::TasksPerProject => 'tasks',
            PlanLimitType::MembersPerProject => 'members',
            default => $type->messageSubject(),
        };
    }

    private function resolveLimitReason(User $user, SubscriptionPlan $plan): string
    {
        if ($plan === SubscriptionPlan::Free && (! $user->hasSubscriptionRecord()
            && $user->hasExpiredTrial())) {
            return PlanLimitExceededException::REASON_TRIAL_EXPIRED;
        }

        return PlanLimitExceededException::REASON_LIMIT_REACHED;
    }

    private function isOverCurrentPlanLimit(int $currentUsage, ?int $maxAllowed): bool
    {
        return $maxAllowed !== null && $currentUsage > $maxAllowed;
    }

    private function loadBillingRelations(User $user): User
    {
        return $user->loadMissing('subscriptions', 'customer');
    }
}
