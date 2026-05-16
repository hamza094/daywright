<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\DataTransferObjects\Project\DashboardProjectFilters;
use App\Models\User;
use App\Services\ApiPaginator;
use Illuminate\Database\Eloquent\Collection;

class UserProjectListingService
{
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
            ->with('stage')
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
    ): ApiPaginator {
        $projects = $this->getUserProjects($user, $filters, $sort);

        return new ApiPaginator(
            $projects->forPage($page, $perPage)->values(),
            $projects->count(),
            $perPage,
            $page,
            [
                'path' => $path,
                'pageName' => 'page',
            ],
        );
    }

    /**
     * @return Collection<int,\App\Models\Project>
     */
    private function filterProjects(User $user, DashboardProjectFilters $filters, string $sort): Collection
    {
        $query = $filters->member
            ? $user->activeMembers()
            : $user->projects();

        return $query
            ->with(['stage', 'user'])
            ->when($filters->abandoned, fn ($query) => $query->trashed())
            ->when($filters->search, fn ($query) => $query->search($filters->search))
            ->sortBy($sort)
            ->get();
    }
}
