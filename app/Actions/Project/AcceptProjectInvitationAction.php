<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\DataTransferObjects\Notification\NotificationActorData;
use App\Enums\Subscription\PlanLimitType;
use App\Models\Project;
use App\Models\User;
use App\Notifications\AcceptInvitation;
use App\Services\Subscription\PlanLimitService;
use Illuminate\Support\Facades\DB;

final readonly class AcceptProjectInvitationAction
{
    public function __construct(
        private PlanLimitService $planLimitService,
    ) {}

    public function execute(Project $project, User $user): void
    {
        $this->planLimitService->executeWithinProjectLimit(
            PlanLimitType::MembersPerProject,
            $project,
            function (Project $lockedProject) use ($user): void {
                $membership = $lockedProject->members()->whereKey($user->getKey())->first();

                // @phpstan-ignore-next-line - Eloquent pivot exposes dynamic properties at runtime
                if ($membership === null || (bool) $membership->pivot->active) {
                    return;
                }

                $lockedProject->members()->updateExistingPivot($user->getKey(), ['active' => true]);

                $lockedProject->recordActivity('invitation_accepted', [$user->id]);

                DB::afterCommit(function () use ($lockedProject, $user): void {
                    $lockedProject->user->notify(new AcceptInvitation(
                        $lockedProject->name,
                        $lockedProject->slug,
                        NotificationActorData::fromUser($user)
                    ));
                });
            },
        );
    }
}
