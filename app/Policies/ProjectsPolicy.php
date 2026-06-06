<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class ProjectsPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function before(User $user): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function manage(User $user, Project $project): bool
    {
        return $user->is($project->user);
    }

    public function access(User $user, Project $project): Response
    {
        return $user->is($project->user) || $project->activeMembers->contains($user->id)
               ? Response::allow()
               : Response::deny("Only Project's owner and members are allowed to access this feature.");
    }

    public function zoomAuthorize(User $user, Project $project): bool
    {
        return $user->is($project->user) && ! $user->oauthConnections()->where('provider', 'zoom')->exists();
    }

    public function canAcceptInvitation(User $user, Project $project): bool
    {
        return $project->members()
            ->where('user_id', $user->id)
            ->wherePivot('active', false)
            ->exists();
    }
}
