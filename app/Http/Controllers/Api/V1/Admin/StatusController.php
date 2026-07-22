<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Admin\TaskStatusRequest;
use App\Http\Resources\Api\V1\Task\TaskStatusResource;
use App\Models\TaskStatus as Status;
use App\Services\Admin\StatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StatusController extends ApiController
{
    public function __construct(private readonly StatusService $statusService) {}

    public function index(): AnonymousResourceCollection
    {
        $statuses = $this->statusService->all();

        return TaskStatusResource::collection($statuses);
    }

    public function store(TaskStatusRequest $request): JsonResponse
    {
        $data = $request->toDto();
        $status = $this->statusService->create($data->toArray());

        return $this->respondCreated(new TaskStatusResource($status));
    }

    public function update(TaskStatusRequest $request, Status $status): JsonResponse
    {
        $data = $request->toDto();
        $status = $this->statusService->update($status, $data->toArray());

        return $this->respondUpdated(new TaskStatusResource($status));
    }

    public function destroy(Status $status): JsonResponse
    {
        $this->statusService->delete($status);

        return $this->respondWithMessage('Status deleted successfully.');
    }
}
