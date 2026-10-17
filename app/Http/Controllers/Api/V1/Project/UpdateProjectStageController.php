<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Documentation\Attributes\ApiError;
use App\Exceptions\Support\ErrorCode;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Project\StageRequest;
use App\Http\Resources\Api\V1\Project\ProjectStageResource;
use App\Models\Project;
use App\Services\Project\ProjectService;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;

final class UpdateProjectStageController extends ApiController
{
    /**
     * Update Project Stage.
     *
     * Updates the stage of a specified project. The new stage is provided in the request payload.
     * Stage changes trigger notifications to all project members.
     * Setting the stage to its current state succeeds without side effects (no notifications) for idempotency.
     * Version increments only when the stage actually changes, not for no-op transitions.
     */
    #[Endpoint(operationId: 'projects.updateStage')]
    #[ApiError(ErrorCode::INVALID_STATE_TRANSITION)]
    #[ApiError(ErrorCode::EDIT_CONFLICT)]
    public function __invoke(Project $project, StageRequest $request, ProjectService $projectService): JsonResponse
    {
        $project = $projectService->updateStageStatus(
            $project,
            $request->projectStageUpdateData(),
            $this->authenticatedUser(),
        );

        return $this->respondUpdated(new ProjectStageResource($project));
    }
}
