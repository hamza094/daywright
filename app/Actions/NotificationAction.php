<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Project;
use App\Models\User;
use Illuminate\Notifications\Notification;

class NotificationAction
{
    public static function send(Notification $notification, Project $project, ?User $actor = null): void
    {
        $users = $project->activeMembers->push($project->user);

        $users
            ->reject(fn (User $user): bool => self::isActor($user, $actor))
            ->each(fn (User $user) => $user->notify($notification));
    }

    private static function isActor(User $user, ?User $actor): bool
    {
        return $actor !== null && $user->is($actor);
    }
}
