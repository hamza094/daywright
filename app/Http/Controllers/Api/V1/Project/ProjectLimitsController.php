<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Services\Project\ProjectService;
use Illuminate\Http\JsonResponse;

final class ProjectLimitsController extends ApiController
{
    public function __construct(private readonly ProjectService $projectService) {}

    /**
     * Retrieve project-scoped subscription limits for the project owner.
     */
    public function __invoke(Project $project): JsonResponse
    {
        return $this->respondWithData(
            $this->projectService->projectLimits($project, $this->authenticatedUser()) ?? []
        );
    }
}
