<?php

declare(strict_types=1);

use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/*Broadcast::channel('activity.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});*/

Broadcast::channel('App.Models.User.{uuid}', fn (User $user, string $uuid): bool => (string) $user->uuid === $uuid);

Broadcast::channel('activities.project.{id}', function ($user, $id): bool {
    $project = Project::where('id', $id)
        ->firstOrFail();

    return (bool) $user->can('access', $project);
});

Broadcast::channel('project.{slug}.conversations', function ($user, $slug): bool {

    $project = Project::with('user')->where('slug', $slug)
        ->firstOrFail();

    return (bool) $user->can('access', $project);
});

Broadcast::channel('deleteConversation.{slug}', function ($user, $slug) {
    $project = Project::with('user')->where('slug', $slug)
        ->firstOrFail();

    if ($user->can('access', $project)) {
        return true;
    }

    return false;
});

Broadcast::channel('typing.{slug}', function ($user, $slug) {
    $project = Project::where('slug', $slug)
        ->firstOrFail();

    if ($user->can('access', $project)) {
        return true;
    }

    return false;
});

Broadcast::channel('chatroom.{slug}', function ($user, $slug) {

    $project = Project::where('slug', $slug)
        ->firstOrFail();

    if ($user->can('access', $project)) {
        return $user;
    }

    return false;
});

Broadcast::channel('meetingStatus.{id}', function ($user, $id): bool {
    $meeting = Meeting::find($id);
    $project = $meeting->project;

    return $meeting && ($user->id === $meeting->user_id ||
                        $user->id === $project->user_id ||
                        $project->activeMembers->contains($user->id));
});

// Authorization for project health updates. Users who can `access` the project may listen.
Broadcast::channel('project.{id}.health', function ($user, $id): bool {
    $project = Project::where('id', $id)->firstOrFail();

    return (bool) $user->can('access', $project);
});
