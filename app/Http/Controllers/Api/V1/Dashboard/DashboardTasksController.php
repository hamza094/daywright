<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\User\UserTasksRequest;
use App\Http\Resources\Api\V1\User\UserTasksResource;
use App\Repository\UserTasksDataRepository;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;

final class DashboardTasksController extends ApiController
{
    /**
     * Return dashboard tasks filtered by ownership, assignment, and status flags.
     *
     * Returns the authenticated user's dashboard task list together with the human-readable filters that were applied.
     */
    #[Endpoint(operationId: 'dashboard.tasks')]
    #[ScrambleResponse(
        status: 200,
        description: 'Filtered dashboard task list with the human-readable filters that were applied.',
        type: 'array{data: array<int, UserTasksResource>, meta: array{applied_filters: array<int, string>, next_cursor: string|null, prev_cursor: string|null, per_page: int}, links: array{next: string|null, prev: string|null}}',
    )]
    public function __invoke(UserTasksRequest $request, UserTasksDataRepository $repository): JsonResponse
    {
        $filters = $request->filters();
        $tasks = $repository->getTasks($this->authenticatedUser()->id, $filters, $request->perPage());

        return $this->respondWithCursorPaginatedData(
            UserTasksResource::collection($tasks),
            $tasks,
            meta: [
                'applied_filters' => UserTasksResource::appliedFilters($filters),
            ],
        );
    }
}
