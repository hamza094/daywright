<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\User;
use App\Repository\DashBoardRepository;
use App\Services\PaginationService;
use Auth;
use Illuminate\Database\Eloquent\Collection;

class DashboardService
{
    public function __construct(protected DashBoardRepository $dashboardRepository) {}

    /**
     * @return Collection<int, \App\Models\Project>|null
     */
    public function getUserProjects(User $user, array $filters, string $sort = 'latest'): Collection
    {
        return $this->filterProjects($user, $filters, $sort);
    }

    /**
     * @return Collection<int, \App\Models\Project>
     */
    public function getDashboardProjects(): Collection
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return collect();
        }

        return $user->projects()
            ->with('stage')
            ->latest()
            ->take(3)
            ->get();
    }

    public function paginateUserProjects(
        User $user,
        array $filters,
        string $sort,
        int $perPage,
        int $page,
        string $path,
    ): PaginationService {
        $projects = $this->getUserProjects($user, $filters, $sort);

        return new PaginationService(
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
    private function filterProjects(User $user, array $filters, string $sort): Collection
    {
        $query = $filters['member']
            ? $user->activeMembers()
            : $user->projects();

        return $query
            ->with(['stage', 'user'])
            ->when($filters['abandoned'], fn ($query) => $query->trashed())
            ->when($filters['search'], fn ($query) => $query->search($filters['search']))
            ->sortBy($sort)
            ->get();
    }
}
