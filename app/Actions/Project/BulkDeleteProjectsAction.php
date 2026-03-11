<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Models\Project;
use App\Services\Api\V1\ProjectService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class BulkDeleteProjectsAction
{
    public function __construct(
        private ProjectService $projectService,
    ) {}

    /**
     * @param  array<int, int>  $projectIds
     */
    public function handle(array $projectIds): void
    {
        if ($projectIds === []) {
            return;
        }

        DB::transaction(function () use ($projectIds): void {
            Project::withTrashed()
                ->whereIn('id', $projectIds)
                ->chunkById(100, function (Collection $projects): void {
                    $projects->each(function (Project $project): void {
                        $this->projectService->forceDeleteIfAbandoned($project);
                    });
                }, column: 'id');
        });
    }
}
