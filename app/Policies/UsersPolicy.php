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
     * - Users share a project membership (either as owner or member)
     */
    public function view(User $authUser, User $targetUser): bool
    {
        // User can view their own profile
        if ($authUser->is($targetUser)) {
            return true;
        }

        // Check if users share any project membership
        // This covers:
        // - Auth user is a member of a project owned by target user
        // - Target user is a member of a project owned by auth user
        // - Both users are members of the same project
        $sharesProject = $authUser->projects()
            ->whereHas('members', fn ($query) => $query->where('user_id', $targetUser->id))
            ->exists();

        // Also check if target user owns any project where auth user is a member
        $targetOwnsSharedProject = $targetUser->projects()
            ->whereHas('members', fn ($query) => $query->where('user_id', $authUser->id))
            ->exists();

        return $sharesProject || $targetOwnsSharedProject;
    }
}
