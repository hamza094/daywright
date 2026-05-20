<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\DataTransferObjects\Project\DashboardProjectFilters;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class UserProjectListingService
{
    private const array PROJECT_LIST_RELATIONS = ['stage'];

    /**
     * @return Collection<int, \App\Models\Project>|null
     */
    public function getUserProjects(User $user, DashboardProjectFilters $filters, string $sort = 'latest'): Collection
    {
        return $this->filterProjects($user, $filters, $sort);
    }

    /**
     * @return Collection<int, \App\Models\Project>
     */
    public function getDashboardProjects(User $user): Collection
    {
        return $user->projects()
            ->with(self::PROJECT_LIST_RELATIONS)
            ->latest()
            ->take(3)
            ->get();
    }

    public function paginateUserProjects(
        User $user,
        DashboardProjectFilters $filters,
        string $sort,
        int $perPage,
        int $page,
        string $path,
    ): LengthAwarePaginator {
        $query = $this->filterProjectsQuery($user, $filters, $sort);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Ensure the paginator uses the requested path for generated links.
        $paginator->withPath($path);

        return $paginator;
    }

    /**
     * @return Collection<int,\App\Models\Project>
     */
    private function filterProjects(User $user, DashboardProjectFilters $filters, string $sort): Collection
    {
        return $this->filterProjectsQuery($user, $filters, $sort)->get();
    }

    /**
     * Build the query for user projects. This preserves the previous
     * filtering semantics but returns a query builder so callers can
     * paginate at the database level.
     *
     * @return Builder<\App\Models\Project>
     */
    private function filterProjectsQuery(User $user, DashboardProjectFilters $filters, string $sort): Builder
    {
        $query = $filters->member
            ? $user->activeMembers()->getQuery()
            : $user->projects()->getQuery();

        return $query
            ->with(self::PROJECT_LIST_RELATIONS)
            ->when($filters->abandoned, fn ($query) => $query->trashed())
            ->when($filters->search, fn ($query) => $query->search($filters->search))
            ->sortBy($sort);
    }
}
