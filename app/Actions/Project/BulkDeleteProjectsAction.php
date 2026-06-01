<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Models\Project;
use App\Services\Project\ProjectService;
use Illuminate\Database\Eloquent\Collection;

final readonly class BulkDeleteProjectsAction
{
    public function __construct(
        private ProjectService $projectService,
    ) {}

    /**
     * @param  array<int, int>  $projectIds
     */
    public function execute(array $projectIds): void
    {
        if ($projectIds === []) {
            return;
        }

        Project::withTrashed()
            ->whereIn('id', $projectIds)
            ->chunkById(100, function (Collection $projects): void {
                $projects->each(function (Project $project): void {
                    $this->projectService->forceDeleteIfAbandoned($project);
                });
            }, column: 'id');
    }
}
