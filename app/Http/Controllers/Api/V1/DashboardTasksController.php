<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\UserTasksRequest;
use App\Http\Resources\Api\V1\User\UserTasksResource;
use App\Repository\UserTasksDataRepository;
use Illuminate\Http\JsonResponse;

final class DashboardTasksController extends ApiController
{
    public function __invoke(UserTasksRequest $request, UserTasksDataRepository $repository): JsonResponse
    {
        $filters = $request->filters();
        $tasks = $repository->getTasks($this->authenticatedUser()->id, $filters);
        $appliedFilters = $repository->appliedFilters($filters);

        return response()->json([
            'data' => UserTasksResource::collection($tasks),
            'meta' => [
                'applied_filters' => $appliedFilters,
                'total' => $tasks->count(),
            ],
        ]);
    }
}
