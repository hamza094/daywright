<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Project;
use App\Models\User;
use Illuminate\Notifications\Notification;

class NotificationAction
{
    public static function send(Notification $notification, Project $project): void
    {
        $users = $project->activeMembers->push($project->user);

        $users
            ->reject(fn (User $user): bool => self::isAuthUser($user))
            ->each(fn (User $user) => $user->notify($notification));
    }

    private static function isAuthUser(User $user): bool
    {
        return auth()->id() === $user->id;
    }
}
