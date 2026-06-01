<?php

declare(strict_types=1);

namespace App\Repository\Admin;

use App\DataTransferObjects\Project\AdminProjectFilters;
use App\Enums\ProjectHealthStatus;
use App\Models\Project;
use App\Models\Stage;
use App\QueryBuilder\Concerns\EscapesLikeWildcards;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProjectFiltersRepository
{
    use EscapesLikeWildcards;

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function filter(AdminProjectFilters $filters, int $perPage): LengthAwarePaginator
    {
        $query = Project::query()
            ->with('stage', 'user')
            ->withCount('tasks', 'activeMembers')
            ->withTrashed()

            ->when($filters->search, function (Builder $query) use ($filters): void {
                $this->applySearchFilter($query, $filters->search);
            })

            ->when($filters->state === 'active', function (Builder $query): void {
                $query->whereNull('deleted_at');
            })
            ->when($filters->state === 'trashed', function (Builder $query): void {
                $query->whereNotNull('deleted_at');
            })

            ->when($filters->members, function (Builder $query): void {
                $query->whereHas('members', function ($subQuery): void {
                    $subQuery->where('project_members.active', true);
                });
            })

            ->when($filters->tasks, function (Builder $query): void {
                $query->has('tasks');
            })

            ->when($filters->stage === 0, function (Builder $query): void {
                $query->where(function (Builder $stageQuery): void {
                    $stageQuery->where('stage_id', 0)
                        ->where(function (Builder $stageStateQuery): void {
                            $stageStateQuery->whereNotNull('postponed')
                                ->orWhere('completed', true);
                        });
                });
            })
            ->when($filters->stage !== null && $filters->stage !== 0, function (Builder $query) use ($filters): void {
                $stage = Stage::query()->find($filters->stage);

                if ($stage) {
                    $query->where('stage_id', $filters->stage);
                }
            })
            ->when($filters->from && $filters->to, function (Builder $query) use ($filters): void {
                $this->applyDateRangeFilter($query, $filters->from, $filters->to);
            })
            ->when($filters->status, function (Builder $query) use ($filters): void {
                $this->applyStatusFilter($query, $filters->status);
            });

        $this->applySort($query, $filters->sort ?? '-created_at');

        return $query->paginate($perPage);
    }

    /**
     * @param  Builder<Project>  $query
     */
    protected function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'created_at' => $query->orderBy('created_at', 'asc'),
            '-created_at' => $query->orderBy('created_at', 'desc'),
            'name' => $query->orderBy('name', 'asc'),
            '-name' => $query->orderBy('name', 'desc'),
            'health_score' => $query->orderBy('health_score', 'asc')->orderBy('created_at', 'desc'),
            '-health_score' => $query->orderBy('health_score', 'desc')->orderBy('created_at', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };
    }

    /**
     * @param  Builder<Project>  $query
     */
    protected function applySearchFilter(Builder $query, string $searchTerm): void
    {
        $query->where(function (Builder $q) use ($searchTerm): void {
            $this->likeContainsLiteral($q, 'name', $searchTerm);

            $q->orWhereHas('user', function (Builder $subQuery) use ($searchTerm): void {
                $this->likeContainsLiteral($subQuery, 'name', $searchTerm);
                $this->likeContainsLiteral($subQuery, 'username', $searchTerm, 'or');
            });
        });
    }

    /**
     * @param  Builder<Project>  $query
     */
    protected function applyDateRangeFilter(Builder $query, string $from, string $to): void
    {
        $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * @param  Builder<Project>  $query
     */
    protected function applyStatusFilter(Builder $query, string $status): void
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
    }
}
