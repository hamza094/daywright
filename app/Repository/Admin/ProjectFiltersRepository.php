<?php

declare(strict_types=1);

namespace App\Repository\Admin;

use App\Models\Project;
use App\Models\Stage;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProjectFiltersRepository
{
    public function filters(Request $request, $perPage, $appliedFilters): array
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

    protected function applySort($query, string $sortDirection, array &$appliedFilters): void
    {
        $query->orderBy('created_at', $sortDirection);
        $appliedFilters[] = "Sort by $sortDirection";
    }

    protected function applySearchFilter($query, string $searchTerm, array &$appliedFilters): void
    {
        $query->where('name', 'like', "%$searchTerm%")
            ->orWhereHas('user', function ($query) use ($searchTerm): void {
                $query->where('name', 'like', "%$searchTerm%")
                    ->orWhere('username', 'like', "%$searchTerm%");
            });

        $appliedFilters[] = 'Search in all';
    }

    protected function applyDateRangeFilter($query, string $from, string $to, array &$appliedFilters): void
    {
        $query->whereBetween('created_at', [$from, $to]);

        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);

        $appliedFilters[] = 'Filter from '.$fromDate->format('Y-m-d').' to '.$toDate->format('Y-m-d');
    }

    protected function applyStatusFilter($query, $status, &$appliedFilters): void
    {
        $query->where('health_status', $status);
        $appliedFilters[] = "Filter by status $status";
    }
}
