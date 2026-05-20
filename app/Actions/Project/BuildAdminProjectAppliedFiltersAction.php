<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\DataTransferObjects\Project\AdminProjectFilters;
use App\Models\Stage;
use Carbon\Carbon;

final class BuildAdminProjectAppliedFiltersAction
{
    /**
     * @return array<int, string>
     */
    public function execute(AdminProjectFilters $filters): array
    {
        $appliedFilters = [];

        if ($filters->sort !== null) {
            $appliedFilters[] = $this->sortLabel($filters->sort);
        }

        if ($filters->search !== null) {
            $appliedFilters[] = 'Search in all';
        }

        if ($filters->state === 'active') {
            $appliedFilters[] = 'Filter by Active';
        }

        if ($filters->state === 'trashed') {
            $appliedFilters[] = 'Filter by Trashed';
        }

        if ($filters->members) {
            $appliedFilters[] = 'Filter by Active Members';
        }

        if ($filters->tasks) {
            $appliedFilters[] = 'Filter by Tasks';
        }

        if ($filters->stage === 0) {
            $appliedFilters[] = 'Filter by Stage: Clo/Pos';
        }

        if ($filters->stage !== null && $filters->stage !== 0) {
            $stageName = Stage::query()->whereKey($filters->stage)->value('name');

            if (is_string($stageName)) {
                $appliedFilters[] = "Filter by Stage: {$stageName}";
            }
        }

        if ($filters->from !== null && $filters->to !== null) {
            $fromDate = Carbon::parse($filters->from);
            $toDate = Carbon::parse($filters->to);

            $appliedFilters[] = 'Filter from '.$fromDate->format('Y-m-d').' to '.$toDate->format('Y-m-d');
        }

        if ($filters->status !== null) {
            $appliedFilters[] = "Filter by status {$filters->status}";
        }

        return $appliedFilters;
    }

    private function sortLabel(string $sort): string
    {
        return match ($sort) {
            'created_at', 'asc' => 'Sort by oldest',
            '-created_at', 'desc' => 'Sort by newest',
            'name' => 'Sort by name (A-Z)',
            '-name' => 'Sort by name (Z-A)',
            'health_score' => 'Sort by health score (low-high)',
            '-health_score' => 'Sort by health score (high-low)',
            default => "Sort by {$sort}",
        };
    }
}
