<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Project\ProjectActivityIndexRequest;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Project;
use App\Services\Project\ProjectActivityListingService;
use Illuminate\Http\JsonResponse;

class ActivityController extends ApiController
{
    /**
     * List project activity entries.
     *
     * Returns the released project activity feed with filter and pagination support.
     */
    public function index(
        Project $project,
        ProjectActivityIndexRequest $request,
        ProjectActivityListingService $projectActivityListingService,
    ): JsonResponse {
        $paginator = $projectActivityListingService->paginate(
            $project,
            $request->filterType(),
            $this->authenticatedUser()->id,
            $request->perPage(),
            $request->pageNumber(),
            $request->url(),
        );

        return ActivityResource::collection($paginator)->response();
    }
}
