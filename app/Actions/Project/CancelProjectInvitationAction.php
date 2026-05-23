<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Models\Project;
use App\Models\User;

final class CancelProjectInvitationAction
{
    public function execute(Project $project, User $user): void
    {
        $project->members()->detach($user);
    }
}
