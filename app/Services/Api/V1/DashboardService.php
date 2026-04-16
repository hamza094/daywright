<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Http\Requests\Api\V1\DashboardProjectRequest;
use App\Models\User;
use App\Repository\DashBoardRepository;
use Auth;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class DashboardService
{
    public function __construct(protected DashBoardRepository $dashboardRepository) {}

    /**
     * Return a paginated list of the user's projects.
     */
    public function getUserProjects(DashboardProjectRequest $request): ?LengthAwarePaginator
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return null;
        }

        $perPage = (int) config('app.project.items_limit');

        $query = $this->filterProjects($user, $request);

        return $query->paginate($perPage);
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

    /**
     * Build the projects query according to supplied filters.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function filterProjects(User $user, DashboardProjectRequest $request)
    {
        $filters = $this->getFilters($request);

        $query = $filters['member']
            ? $user->activeMembers()
            : $user->projects();

        return $query
            ->with(['stage', 'user'])
            ->when($filters['abandoned'], fn ($query) => $query->trashed())
            ->when($filters['search'], fn ($query) => $query->search($filters['search']))
            ->sortBy($filters['sort']);
    }

    /**
     * @return array<string,mixed>
     */
    private function getFilters(DashboardProjectRequest $request): array
    {
        return [
            'search' => $request->validated('search'),
            'sort' => $request->validated('sort', 'latest'),
            'member' => $request->validated('member', false),
            'abandoned' => $request->validated('abandoned', false),
        ];
    }
}
