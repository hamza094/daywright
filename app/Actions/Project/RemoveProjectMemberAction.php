<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RemoveProjectMemberAction
{
    public function execute(Project $project, User $user): void
    {
        if (! $project->activeMembers()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'user' => 'This user is not an active member of the project.',
            ]);
        }

        DB::transaction(function () use ($project, $user): void {
            $project->members()->detach($user);
            $project->recordActivity('member_removed', [$user->id]);
        });
    }
}
