<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Admin\TaskStatusRequest;
use App\Http\Resources\Api\V1\Task\TaskStatusResource;
use App\Models\TaskStatus as Status;
use Illuminate\Http\JsonResponse;

class StatusController extends ApiController
{
    public function index()
    {
        $statuses = Status::all();

        return TaskStatusResource::collection($statuses);
    }

    public function store(TaskStatusRequest $request): JsonResponse
    {
        $status = Status::create($request->validated());

        return $this->respondCreated(new TaskStatusResource($status));
    }

    public function update(TaskStatusRequest $request, Status $status): JsonResponse
    {
        $status->update($request->validated());

        return $this->respondUpdated(new TaskStatusResource($status));
    }

    public function destroy(Status $status): JsonResponse
    {
        $status->delete();

        return $this->respondWithMessage('Status deleted successfully');
    }
}
