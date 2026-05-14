<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\UserTasksRequest;
use App\Http\Resources\Api\V1\User\UserTasksResource;
use App\Repository\UserTasksDataRepository;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;

final class DashboardTasksController extends ApiController
{
    /**
     * Return dashboard tasks filtered by ownership, assignment, and status flags.
     *
     * Returns the authenticated user's dashboard task list together with the human-readable filters that were applied.
     */
    #[ScrambleResponse(
        status: 200,
        description: 'Filtered dashboard task list with the human-readable filters that were applied.',
        type: 'array{data: array<int, UserTasksResource>, meta: array{applied_filters: array<int, string>, total: int}}',
    )]
    public function __invoke(UserTasksRequest $request, UserTasksDataRepository $repository): JsonResponse
    {
        $filters = $request->filters();
        $tasks = $repository->getTasks($this->authenticatedUser()->id, $filters);
        $appliedFilters = $repository->appliedFilters($filters);

        return $this->respondWithData(
            UserTasksResource::collection($tasks),
            meta: [
                'applied_filters' => $appliedFilters,
                'total' => $tasks->count(),
            ],
        );
    }
}
