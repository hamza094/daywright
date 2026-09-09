<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UsersPolicy
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

    public function owner(User $user): bool
    {
        return $user->is(auth()->user());
    }

    /**
     * Determine if the authenticated user can view the target user's profile.
     * Allows viewing if:
     * - User is viewing their own profile
     * - Users share at least one active project (owned or actively-membered by each user)
     */
    public function view(User $authUser, User $targetUser): bool
    {
        if ($authUser->is($targetUser)) {
            return true;
        }

        // Collect all project IDs the auth user has access to:
        // owned projects (via projects.user_id) + active pivot memberships.
        $authProjectIds = $authUser->projects()->pluck('id')
            ->merge($authUser->members(true)->pluck('projects.id'))
            ->unique();

        if ($authProjectIds->isEmpty()) {
            return false;
        }

        // Check if the target user owns or is an active member of any of those projects.
        $ownsSharedProject = $targetUser->projects()
            ->whereIn('id', $authProjectIds)
            ->exists();

        if ($ownsSharedProject) {
            return true;
        }

        return $targetUser->members(true)
            ->whereIn('projects.id', $authProjectIds)
            ->exists();
    }
}
