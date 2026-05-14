<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Services\Project\ProjectService;
use Illuminate\Http\JsonResponse;

final class RestoreProjectController extends ApiController
{
    /**
     * Restore a soft-deleted project.
     *
     * Re-activates a previously abandoned project.
     */
    public function __invoke(Project $project, ProjectService $projectService): JsonResponse
    {
        $projectService->restoreProject($project);

        return $this->respondWithMessage($project->name.' restored successfully');
    }
}
