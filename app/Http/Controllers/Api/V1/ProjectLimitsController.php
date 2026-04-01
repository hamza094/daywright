<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Services\Api\V1\ProjectService;
use Illuminate\Http\JsonResponse;

final class ProjectLimitsController extends ApiController
{
    public function __construct(private readonly ProjectService $projectService) {}

    /**
     * Retrieve project-scoped subscription limits for the project owner.
     */
    public function __invoke(Project $project): JsonResponse
    {
        return response()->json([
            'message' => 'Project limits retrieved successfully',
            'limits' => $this->projectService->projectLimits($project, $this->authenticatedUser()) ?? [],
        ], 200);
    }
}
