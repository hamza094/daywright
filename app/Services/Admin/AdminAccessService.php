<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminAccessService
{
    public function grantAdminAccess(User $user, ?User $performedBy = null): void
    {
        $this->ensureUserNotAdmin($user);

        DB::transaction(function () use ($user, $performedBy): void {
            $this->saveGrantAccess($user, $performedBy);
        });
    }

    public function revokeAdminAccess(User $user, ?User $revokedBy = null): void
    {
        DB::transaction(function () use ($user, $revokedBy): void {
            $lockedUser = $this->lockAndEnsureUserIsAdmin($user);

            $this->ensureNotLastAdmin($lockedUser);

            $this->clearGrantAccess($lockedUser, $revokedBy);

            $this->deletePersonalAccessTokens($lockedUser);

            $this->rotateRememberTokenForUser($lockedUser);
        });
    }

    private function ensureNotLastAdmin(User $user): void
    {
        $hasAnotherAdmin = User::query()
            ->where('is_admin', true)
            ->where('id', '!=', $user->id)
            ->lockForUpdate()
            ->exists();

        if (! $hasAnotherAdmin) {
            throw ValidationException::withMessages([
                'user' => 'Cannot revoke the last admin account.',
            ]);
        }
    }

    private function lockAndEnsureUserIsAdmin(User $user): User
    {
        $lockedUser = User::query()
            ->whereKey($user->id)
            ->lockForUpdate()
            ->first();

        if (! $lockedUser instanceof User || ! $lockedUser->isAdmin()) {
            throw ValidationException::withMessages([
                'user' => 'Selected user does not have admin access.',
            ]);
        }

        return $lockedUser;
    }

    private function ensureUserNotAdmin(User $user): void
    {
        if ($user->isAdmin()) {
            throw ValidationException::withMessages([
                'user' => 'Selected user already has admin access.',
            ]);
        }
    }

    private function saveGrantAccess(User $user, ?User $performedBy = null): void
    {
        $user->update([
            'is_admin' => true,
            'admin_granted_at' => now(),
            'admin_granted_by' => $performedBy?->id,
            'admin_revoked_at' => null,
            'admin_revoked_by' => null,
        ]);
    }

    private function clearGrantAccess(User $user, ?User $revokedBy = null): void
    {
        $user->update([
            'is_admin' => false,
            'admin_revoked_at' => now(),
            'admin_revoked_by' => $revokedBy?->id,
        ]);
    }

    private function deletePersonalAccessTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    private function rotateRememberTokenForUser(User $user): void
    {
        $user->update([
            'remember_token' => Str::random(60),
        ]);
    }
}
