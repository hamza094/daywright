<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Project;
use App\Repository\ProjectRepository;
use F9Web\ApiResponseHelpers;

class ActivityController extends ApiController
{
    use ApiResponseHelpers;

    public function index(Project $project, ProjectRepository $repository)
    {
        $activities = $repository->filterActivities(
            $project->activities
        );

        if ($activities->isEmpty()) {
            return response()->json(
                ['message' => 'No related activities found'], 200);
        }

        return ActivityResource::collection($activities)->paginate(10);
    }
}
