<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Enums\Subscription\PlanLimitType;
use App\Enums\Subscription\SubscriptionPlan;
use App\Exceptions\Subscription\PlanLimitExceededException;
use App\Models\User;

final readonly class BuildPlanLimitExceededExceptionAction
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
            message: $this->limitExceededMessage($type, $plan),
            limitType: $type->exceptionKey(),
            reason: $this->resolveLimitReason($user, $plan),
            currentUsage: $currentUsage,
            maxAllowed: $maxAllowed,
            limitScope: $type->requiresProject()
                ? PlanLimitExceededException::SCOPE_PROJECT
                : PlanLimitExceededException::SCOPE_ACCOUNT,
            limitOwnerId: (int) $user->getKey(),
        );
    }

    private function limitExceededMessage(PlanLimitType $type, SubscriptionPlan $plan): string
    {
        if ($type->requiresProject()) {
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

    private function projectLimitMessageSubject(PlanLimitType $type): string
    {
        return match ($type) {
            PlanLimitType::ActiveTasksPerProject => 'active tasks',
            PlanLimitType::MembersPerProject => 'members',
            default => $type->messageSubject(),
        };
    }

    private function resolveLimitReason(User $user, SubscriptionPlan $plan): string
    {
        if ($plan === SubscriptionPlan::Free && (! $user->hasSubscriptionRecord()
            && ($user->hasExpiredTrial() || $user->hasExpiredTrial($user->subscriptionName())))) {
            return PlanLimitExceededException::REASON_TRIAL_EXPIRED;
        }

        return PlanLimitExceededException::REASON_LIMIT_REACHED;
    }

    private function loadBillingRelations(User $user): User
    {
        return $user->loadMissing('subscriptions', 'customer');
    }
}
