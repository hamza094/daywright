<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Services\Project\ProjectService;
use Illuminate\Http\JsonResponse;

final class ForceDeleteProjectController extends ApiController
{
    public function __invoke(Project $project, ProjectService $projectService): JsonResponse
    {
        $deleted = $projectService->forceDeleteIfAbandoned($project);

        if (! $deleted) {
            return response()->json([
                'message' => 'Only abandoned projects can be deleted permanently.',
            ], 403);
        }

        return response()->json([
            'message' => 'Project deleted successfully',
        ], 200);
    }
}
