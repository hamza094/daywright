<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Http\Requests\Api\V1\DashboardProjectRequest;
use App\Models\User;
use App\Repository\DashBoardRepository;
use Auth;
use Illuminate\Database\Eloquent\Collection;

class DashboardService
{
    public function __construct(protected DashBoardRepository $dashboardRepository) {}

    /**
     * @return Collection<int, \App\Models\Project>|null
     */
    public function getUserProjects(DashboardProjectRequest $request): ?Collection
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return null;
        }

        return $this->filterProjects($user, $request);
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
     * @return Collection<int,\App\Models\Project>
     */
    private function filterProjects(User $user, DashboardProjectRequest $request): Collection
    {
        $filters = $this->getFilters($request);

        $query = $filters['member']
            ? $user->activeMembers()
            : $user->projects();

        return $query
            ->with(['stage', 'user'])
            ->when($filters['abandoned'], fn ($query) => $query->trashed())
            ->when($filters['search'], fn ($query) => $query->search($filters['search']))
            ->sortBy($filters['sort'])
            ->get();
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
