<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\DataTransferObjects\Notification\NotificationActorData;
use App\Models\Project;
use App\Models\User;
use App\Notifications\AcceptInvitation;
use Illuminate\Support\Facades\DB;

final class AcceptProjectInvitationAction
{
    private const int TRANSACTION_RETRY_ATTEMPTS = 5;

    public function execute(Project $project, User $user): void
    {
        DB::transaction(function () use ($project, $user): void {
            $lockedProject = $this->lockProject($project);
            $membership = $lockedProject->members()->whereKey($user->getKey())->first();

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
        }, attempts: self::TRANSACTION_RETRY_ATTEMPTS);
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
