<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RemoveProjectMemberAction
{
    private const int TRANSACTION_RETRY_ATTEMPTS = 5;

    public function execute(Project $project, User $user): void
    {
        DB::transaction(function () use ($project, $user): void {
            $lockedProject = $this->lockProject($project);
            $membership = $lockedProject->members()->whereKey($user->getKey())->first();

            if ($membership === null) {
                return;
            }

            // @phpstan-ignore-next-line - Eloquent pivot exposes dynamic properties at runtime
            if (! (bool) $membership->pivot->active) {
                throw ValidationException::withMessages([
                    'user' => 'This user is not an active member of the project.',
                ]);
            }

            $lockedProject->members()->detach($user->getKey());
            $lockedProject->recordActivity('member_removed', [$user->id]);
        }, attempts: self::TRANSACTION_RETRY_ATTEMPTS);
    }

    private function lockProject(Project $project): Project
    {
        /** @var Project */
        return Project::query()
            ->whereKey($project->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
