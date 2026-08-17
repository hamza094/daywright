<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Actions\ProjectMetrics\ProjectHealthRecalculationAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Project\ProjectInsightsRequest;
use App\Http\Resources\Api\V1\Project\ProjectInsightsResource;
use App\Models\Project;
use App\Services\Project\ProjectInsightsService;
use Dedoc\Scramble\Attributes\Endpoint;

class ProjectInsightsController extends ApiController
{
    public function __construct(
        private readonly ProjectInsightsService $insightService,
        private readonly ProjectHealthRecalculationAction $healthRecalculationAction
    ) {}

    /**
     * Get actionable insights for a project.
     *
     * What this endpoint does:
     * - Aggregates calculated insights for the given project across one or more sections
     *   (health, task-health, collaboration, risk, stage).
     * - You can filter which sections are returned using the `sections[]` query parameter.
     * - If `sections[]` is omitted, all supported sections are returned.
     */
    #[Endpoint(operationId: 'projects.insights')]
    public function index(ProjectInsightsRequest $request, Project $project): ProjectInsightsResource
    {
        $data = $request->toDto();
        $sections = $data->sections;

        $insights = $this->insightService->getInsights($project, $sections);

        $this->healthRecalculationAction->execute($project, $sections);

        return new ProjectInsightsResource([
            'project' => $project,
            'insights' => $insights,
            'sections' => $sections,
        ]);
    }
}
