<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Models\Project;
use App\Services\Audit\AuditLogService;
use App\Services\Project\ProjectService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class BulkDeleteProjectsAction
{
    public function __construct(
        private ProjectService $projectService,
        private AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array<int, int>  $projectIds
     */
    public function execute(array $projectIds): void
    {
        if ($projectIds === []) {
            return;
        }

        $deletedProjectIds = [];

        DB::transaction(function () use ($projectIds, &$deletedProjectIds): void {
            Project::withTrashed()
                ->whereIn('id', $projectIds)
                ->chunkById(100, function (Collection $projects) use (&$deletedProjectIds): void {
                    $projects->each(function (Project $project) use (&$deletedProjectIds): void {
                        $this->projectService->forceDeleteIfAbandoned($project);
                        $deletedProjectIds[] = $project->id;
                    });
                }, column: 'id');

            $this->auditLogService->log(
                event: 'destruction.bulk_projects_deleted',
                auditable: null,
                oldValues: [
                    'project_ids' => $deletedProjectIds,
                    'count' => count($deletedProjectIds),
                ],
                newValues: null,
                metadata: [
                    'bulk_operation' => true,
                ]
            );
        });
    }
}
