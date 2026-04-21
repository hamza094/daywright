<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Project;
use App\Repository\ProjectRepository;
use F9Web\ApiResponseHelpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityController extends Controller
{
    use ApiResponseHelpers;

    public function index(
        Project $project,
        ProjectRepository $repository,
    ): AnonymousResourceCollection|JsonResponse {
        $activities = $repository->filterActivities($project->activities());
        $paginatedActivities = $activities->paginate(10);

        if ($paginatedActivities->isEmpty()) {
            return response()->json(['message' => 'No related activities found'], 200);
        }

        return ActivityResource::collection($paginatedActivities);
    }
}
