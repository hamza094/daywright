<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Models\Project;
use App\Models\User;
use App\Notifications\AcceptInvitation;
use Illuminate\Support\Facades\DB;

final class AcceptProjectInvitationAction
{
    public function execute(Project $project, User $user): void
    {
        DB::transaction(function () use ($project, $user): void {
            $user->members()->updateExistingPivot($project, ['active' => true]);

            $project->recordActivity('invitation_accepted', [$user->id]);

            $project->user->notify(new AcceptInvitation(
                $project->name,
                $project->path(),
                $user->getNotifierData()
            ));
        });
    }
}
