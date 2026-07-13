<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class InvitationRepository
{
    private const int SEARCH_LIMIT = 5;

    /**
     * Search for users who can be invited to a project.
     *
     * Excludes the project owner and existing project members.
     *
     * @return EloquentCollection<int, User>
     */
    public function searchInvitableUsers(Project $project, string $searchTerm): EloquentCollection
    {
        $searchPattern = $searchTerm.'%';

        return User::query()
            ->where('id', '!=', $project->user_id)
            ->whereDoesntHave('members', fn (Builder $memberQuery) => $memberQuery->whereKey($project->id))
            ->whereAny(['name', 'email'], 'LIKE', $searchPattern)
            ->select(['uuid', 'name', 'username', 'email', 'avatar_path'])
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->get();
    }
}
