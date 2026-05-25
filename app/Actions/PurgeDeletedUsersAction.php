<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PurgeDeletedUsersAction
{
    /**
     * Permanently delete users who have been soft deleted for more than 15 days.
     */
    public function execute(): void
    {
        User::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(15))
            ->get()
            ->each(fn (User $user) => DB::transaction(fn () => $this->handleUserProjects($user)));
    }

    private function handleUserProjects(User $user): void
    {
        $user->projects()->withTrashed()->get()->each(function ($project) use ($user): void {
            if ($this->permanentDeleteProject($project)) {
                return;
            }

            $admin = $this->findAdminForProject($project, $user->id);

            if ($admin instanceof User) {
                $project->user_id = $admin->id;
                $project->save();
            }

            $project->delete();
        });

        $user->forceDelete();
    }

    private function permanentDeleteProject(Project $project): bool
    {
        if ($project->members()->count() === 0) {
            $project->forceDelete();

            return true;
        }

        return false;
    }

    private function findAdminForProject(Project $project, int $excludeUserId): ?User
    {
        return $project->members()
            ->where('users.is_admin', true)
            ->where('users.id', '!=', $excludeUserId)
            ->first()
            ?? User::query()->where('is_admin', true)->where('id', '!=', $excludeUserId)->first()
            ?? $project->members()->where('users.id', '!=', $excludeUserId)->first();
    }
}
