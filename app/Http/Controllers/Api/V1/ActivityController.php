<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\BuildPaginatedPayloadAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Project;
use App\Repository\ProjectRepository;
use F9Web\ApiResponseHelpers;
use Illuminate\Http\JsonResponse;

class ActivityController extends Controller
{
    use ApiResponseHelpers;

    public function index(
        Project $project,
        ProjectRepository $repository,
        BuildPaginatedPayloadAction $buildPaginatedPayloadAction,
    ): JsonResponse {
        $activities = $repository->filterActivities($project->activities());
        $paginatedActivities = $activities->paginate(10);
        $activitiesPayload = $buildPaginatedPayloadAction->handle($paginatedActivities, ActivityResource::class);

        if ($paginatedActivities->isEmpty()) {
            return response()->json(['message' => 'No related activities found'], 200);
        }

        return response()->json($activitiesPayload, 200);
    }
}
