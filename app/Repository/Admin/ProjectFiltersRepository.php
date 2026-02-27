<?php

declare(strict_types=1);

namespace App\Repository\Admin;

use App\Enums\ProjectHealthStatus;
use App\Http\Requests\Api\V1\Admin\ProjectFilterRequest;
use App\Models\Project;
use App\Models\Stage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ProjectFiltersRepository
{
    /**
     * @param  array<int, string>  $appliedFilters
     * @return array{projects: \Illuminate\Contracts\Pagination\LengthAwarePaginator, appliedFilters: array<int, string>}
     */
    public function filters(ProjectFilterRequest $request, int $perPage, array $appliedFilters): array
    {

        $projects = Project::with('stage', 'user')
            ->withCount('tasks', 'activeMembers')
            ->withTrashed()
            ->when($request->sort, function ($query, $sortDirection) use (&$appliedFilters): void {
                $this->applySort($query, $sortDirection, $appliedFilters);
            })

            ->when($request->search, function ($query) use ($request, &$appliedFilters): void {
                $this->applySearchFilter($query, $request->search, $appliedFilters);
            })

            ->when($request->filter === 'active', function ($query) use (&$appliedFilters): void {
                $query->whereNull('deleted_at');
                $appliedFilters[] = 'Filter by Active';
            })
            ->when($request->filter === 'trashed', function ($query) use (&$appliedFilters): void {
                $query->whereNotNull('deleted_at');
                $appliedFilters[] = 'Filter by Trashed';

            })

            ->when($request->members, function ($query) use (&$appliedFilters): void {
                $query->whereHas('members', function ($subQuery): void {
                    $subQuery->where('project_members.active', true);
                });
                $appliedFilters[] = 'Filter by Active Members';
            })

            ->when($request->tasks, function ($query) use (&$appliedFilters): void {
                $query->has('tasks');
                $appliedFilters[] = 'Filter by Active Members';

            })
            ->when($request->stage === '0', function ($query) use (&$appliedFilters): void {
                $query->where(function ($query): void {
                    $query->where('stage_id', 0)
                        ->where(function ($query): void {
                            $query->whereNotNull('postponed')
                                ->orWhere('completed', true);
                        });
                });
                $appliedFilters[] = 'Filter by Stage: Clo/Pos';
            })
            ->when($request->stage, function ($query, $stageId) use (&$appliedFilters): void {
                $stage = Stage::find($stageId);
                if ($stage) {
                    $query->where('stage_id', $stageId);
                    $appliedFilters[] = "Filter by Stage: {$stage->name}";
                }
            })
            ->when($request->from && $request->to, function ($query) use ($request, &$appliedFilters): void {
                $this->applyDateRangeFilter($query, $request->from, $request->to, $appliedFilters);
            })
            ->when($request->status, function ($query) use ($request, &$appliedFilters): void {
                $this->applyStatusFilter($query, $request->status, $appliedFilters);
            })
            ->paginate($perPage);

        return [
            'projects' => $projects,
            'appliedFilters' => $appliedFilters,
        ];

    }

    /**
     * @param  Builder<Project>  $query
     * @param  array<int, string>  $appliedFilters
     */
    protected function applySort(Builder $query, string $sortDirection, array &$appliedFilters): void
    {
        $query->orderBy('created_at', $sortDirection);
        $appliedFilters[] = "Sort by $sortDirection";
    }

    /**
     * @param  Builder<Project>  $query
     * @param  array<int, string>  $appliedFilters
     */
    protected function applySearchFilter(Builder $query, string $searchTerm, array &$appliedFilters): void
    {
        $escaped = str_replace(['%', '_'], ['\%', '\_'], $searchTerm);

        $query->where(function (Builder $q) use ($escaped): void {
            $q->where('name', 'like', "%{$escaped}%")
                ->orWhereHas('user', function (Builder $subQuery) use ($escaped): void {
                    $subQuery->where('name', 'like', "%{$escaped}%")
                        ->orWhere('username', 'like', "%{$escaped}%");
                });
        });

        $appliedFilters[] = 'Search in all';
    }

    /**
     * @param  Builder<Project>  $query
     * @param  array<int, string>  $appliedFilters
     */
    protected function applyDateRangeFilter(Builder $query, string $from, string $to, array &$appliedFilters): void
    {
        $query->whereBetween('created_at', [$from, $to]);

        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);

        $appliedFilters[] = 'Filter from '.$fromDate->format('Y-m-d').' to '.$toDate->format('Y-m-d');
    }

    /**
     * @param  Builder<Project>  $query
     * @param  array<int, string>  $appliedFilters
     */
    protected function applyStatusFilter(Builder $query, string $status, array &$appliedFilters): void
    {
        $normalizedStatus = mb_strtolower($status);

        match ($normalizedStatus) {
            ProjectHealthStatus::HOT->value => $query->where('health_score', '>=', 75),
            ProjectHealthStatus::WARM->value => $query->whereBetween('health_score', [45, 74.999999]),
            ProjectHealthStatus::COLD->value => $query->where(function (Builder $subQuery): void {
                $subQuery->whereNull('health_score')
                    ->orWhere('health_score', '<', 45);
            }),
            default => null,
        };

        $appliedFilters[] = "Filter by status {$normalizedStatus}";
    }
}
