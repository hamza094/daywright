<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Override;

// use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * @implements WithMapping<Project>
 */
class ProjectsExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(public Project $project) {}

    /**
     * @return Builder<Project>
     */
    #[Override]
    public function query(): Builder
    {
        return Project::query()->where('slug', $this->project->slug);
    }

    /**
     * @return array<int, string>
     */
    #[Override]
    public function headings(): array
    {
        return [
            'Slug',
            'Name',
            'About',
            'Notes',
            'Stage Updated At',
            'Current Stage',
            'Total Tasks',
            'Total Active Members',
            'Status',
            'Owner Name',
            'Owner Mail Address',
            'Created_at',
        ];
    }

    /**
     * @param  Project  $row
     * @return array<int, string|int|null>
     */
    #[Override]
    public function map($row): array
    {
        /** @var Project $project */
        $project = $row;

        return [
            $project->slug,
            $project->name,
            $project->about,
            $project->notes,
            $project->stage_updated_at?->toDateTimeString(),
            $project->stage->name,
            $project->tasks()->count(),
            $project->activeMembers()->count(),
            $project->state(),
            $project->user->name,
            $project->user->email,
            $project->created_at->toDateTimeString(),

        ];
    }
}
