<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Project;
use App\Models\User;

trait ProjectInvitationHelpers
{
    protected function sendInvitationToUser(Project $project, User $user): void
    {
        $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1ProjectRoute('send.invitation', $project), [
            'email' => $user->email,
        ]);
    }

    protected function addMember(Project $project, User $user): void
    {
        $project->members()->syncWithoutDetaching([
            $user->id => ['active' => true],
        ]);
    }

    protected function inviteAndActivateUser(Project $project, User $user): void
    {
        $project->invite($user);
        $project->members()->updateExistingPivot($user->id, ['active' => true]);
    }
}
