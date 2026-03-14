<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Subscription;

use App\Enums\SubscriptionPlan;
use App\Enums\TaskStatus;
use App\Exceptions\Subscription\PlanLimitExceededException;
use App\Models\Project;
use App\Models\User;
use Laravel\Paddle\Subscription as PaddleSubscription;

final readonly class PlanLimitService
{
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

    public function canCreateProject(User $user): bool
    {
        return $this->withinLimit(
            usage: $this->ownedProjectCount($user),
            maxAllowed: $this->plan($user)->maxProjects(),
        );
    }

    public function canCreateTask(User $user, Project $project): bool
    {
        return $this->withinLimit(
            usage: $this->activeTaskCount($project),
            maxAllowed: $this->plan($user)->maxTasksPerProject(),
        );
    }

    public function canInviteMember(User $user, Project $project): bool
    {
        return $this->withinLimit(
            usage: $this->activeMemberCount($project),
            maxAllowed: $this->plan($user)->maxMembersPerProject(),
        );
    }

    public function canCreateMeeting(User $user): bool
    {
        return $this->withinLimit(
            usage: $this->createdMeetingCount($user),
            maxAllowed: $this->plan($user)->maxCreatedMeetings(),
        );
    }

    public function canCreateApiToken(User $user): bool
    {
        return $this->withinLimit(
            usage: $this->apiTokenCount($user),
            maxAllowed: $this->plan($user)->maxApiTokens(),
        );
    }

    /**
     * @return array<string, array{used: int|null, max: int|null}>
     */
    public function usage(User $user, ?Project $project = null): array
    {
        $plan = $this->plan($user);

        return [
            'projects' => [
                'used' => $this->ownedProjectCount($user),
                'max' => $plan->maxProjects(),
            ],
            'active_tasks_per_project' => [
                'used' => $project ? $this->activeTaskCount($project) : null,
                'max' => $plan->maxTasksPerProject(),
            ],
            'members_per_project' => [
                'used' => $project ? $this->activeMemberCount($project) : null,
                'max' => $plan->maxMembersPerProject(),
            ],
            'created_meetings' => [
                'used' => $this->createdMeetingCount($user),
                'max' => $plan->maxCreatedMeetings(),
            ],
            'api_tokens' => [
                'used' => $this->apiTokenCount($user),
                'max' => $plan->maxApiTokens(),
            ],
        ];
    }

    public function assertCanCreateProject(User $user): void
    {
        $this->assertLimit(
            user: $user,
            limitType: 'projects',
            currentUsage: $this->ownedProjectCount($user),
            maxAllowed: $this->plan($user)->maxProjects(),
            messageSubject: 'projects',
        );
    }

    public function assertCanCreateTask(User $user, Project $project): void
    {
        $this->assertLimit(
            user: $user,
            limitType: 'active_tasks_per_project',
            currentUsage: $this->activeTaskCount($project),
            maxAllowed: $this->plan($user)->maxTasksPerProject(),
            messageSubject: 'active tasks for this project',
        );
    }

    public function assertCanInviteMember(User $user, Project $project): void
    {
        $this->assertLimit(
            user: $user,
            limitType: 'members',
            currentUsage: $this->activeMemberCount($project),
            maxAllowed: $this->plan($user)->maxMembersPerProject(),
            messageSubject: 'members for this project',
        );
    }

    public function assertCanCreateMeeting(User $user): void
    {
        $this->assertLimit(
            user: $user,
            limitType: 'meetings',
            currentUsage: $this->createdMeetingCount($user),
            maxAllowed: $this->plan($user)->maxCreatedMeetings(),
            messageSubject: 'created meetings',
        );
    }

    public function assertCanCreateApiToken(User $user): void
    {
        $this->assertLimit(
            user: $user,
            limitType: 'api_tokens',
            currentUsage: $this->apiTokenCount($user),
            maxAllowed: $this->plan($user)->maxApiTokens(),
            messageSubject: 'API tokens',
        );
    }

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
        if ($maxAllowed === null) {
            return true;
        }

        return $usage < $maxAllowed;
    }

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

    private function ownedProjectCount(User $user): int
    {
        return $user->projects()->count();
    }

    private function activeTaskCount(Project $project): int
    {
        return $project->tasks()
            ->whereIn('status_id', TaskStatus::active())
            ->count();
    }

    private function activeMemberCount(Project $project): int
    {
        return $project->activeMembers()->count();
    }

    private function createdMeetingCount(User $user): int
    {
        return $user->meetings()->count();
    }

    private function apiTokenCount(User $user): int
    {
        return $user->tokens()->count();
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
