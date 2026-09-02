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
     * - Users share a project membership
     * - Users share a team membership
     */
    public function view(User $authUser, User $targetUser): bool
    {
        // User can view their own profile
        if ($authUser->is($targetUser)) {
            return true;
        }

        // Check for shared project membership
        $sharesProject = $authUser->projects()
            ->whereHas('members', fn ($query) => $query->where('user_id', $targetUser->id))
            ->exists();

        // Check for shared team membership
        $sharesTeam = $authUser->teams()
            ->whereHas('members', fn ($query) => $query->where('user_id', $targetUser->id))
            ->exists();

        return $sharesProject || $sharesTeam;
    }
}
