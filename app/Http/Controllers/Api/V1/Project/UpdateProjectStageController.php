<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\StageRequest;
use App\Http\Resources\Api\V1\Project\ProjectStageResource;
use App\Models\Project;
use App\Services\Project\FeatureService;
use App\Services\Project\ProjectService;
use Illuminate\Http\JsonResponse;

final class UpdateProjectStageController extends ApiController
{
    public function __construct(private readonly FeatureService $featureService) {}

    /**
     * Update Project Stage.
     *
     * Updates the stage of a specified project. The new stage is provided in the request payload.
     */
    public function __invoke(Project $project, StageRequest $request, ProjectService $projectService): JsonResponse
    {
        $validated = $request->validated();

        $this->featureService->updateStageStatus($project, $validated);
        $projectService->sendNotification($project, $this->authenticatedUser());

        return $this->respondUpdated(new ProjectStageResource($project));
    }
}
