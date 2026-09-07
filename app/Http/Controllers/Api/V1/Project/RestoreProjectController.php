<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Services\Project\ProjectService;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;

final class RestoreProjectController extends ApiController
{
    /**
     * Restore a soft-deleted (abandoned) project.
     *
     * This endpoint only applies to abandoned (soft-deleted) projects. It re-activates the project
     * and makes it accessible again to members.
     */
    #[Endpoint(operationId: 'projects.restore')]
    public function __invoke(Project $project, ProjectService $projectService): JsonResponse
    {
        $projectService->restoreProject($project);

        return $this->respondWithMessage($project->name.' restored successfully');
    }
}
