<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Project;
use App\Models\User;
use Illuminate\Notifications\Notification;

final class NotifyProjectMembersAction
{
    public function execute(Notification $notification, Project $project, ?User $actor = null): void
    {
        $users = $project->activeMembers->push($project->user);

        $users
            ->reject(fn (User $user): bool => $this->isActor($user, $actor))
            ->each(fn (User $user) => $user->notify($notification));
    }

    private function isActor(User $user, ?User $actor): bool
    {
        return $actor instanceof User && $user->is($actor);
    }
}
