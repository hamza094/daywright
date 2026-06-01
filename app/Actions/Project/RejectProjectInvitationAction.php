<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Models\Project;
use App\Models\User;

final class RejectProjectInvitationAction
{
    public function execute(Project $project, User $user): void
    {
        // Delegate to CancelProjectInvitationAction to avoid duplicated logic.
        (new CancelProjectInvitationAction)->execute($project, $user);
    }
}
