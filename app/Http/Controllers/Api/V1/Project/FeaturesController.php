<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Exports\ProjectsExport;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\StageRequest;
use App\Http\Resources\Api\V1\Project\ProjectStageResource;
use App\Models\Project;
use App\Services\Api\V1\FeatureService;
use App\Services\Api\V1\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FeaturesController extends ApiController
{
    /**
     * Service For Project Feature
     * App\Service\FeatureService
     */
    public function __construct(private FeatureService $featureService) {}

    /**
     * Update Project Stage.
     *
     * Updates the stage of a specified project. The new stage is provided in the request payload.
     */
    public function stage(Project $project, StageRequest $request, ProjectService $service): JsonResponse
    {
        $validated = $request->validated();

        $this->featureService->updateStageStatus($project, $validated);

        $service->sendNotification($project, $this->authenticatedUser());

        return response()->json([
            'message' => 'Project Stage Updated Successfully',
            'project' => new ProjectStageResource($project),
        ], 200);
    }

    public function export(Project $project): BinaryFileResponse
    {
        // $this->featureService->excelExport($project);
        return Excel::download(new ProjectsExport($project), "Project $project->name.xls");
    }
}
