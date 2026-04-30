<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\TaskStatusRequest;
use App\Http\Resources\Api\V1\Task\TaskStatusResource;
use App\Models\TaskStatus as Status;
use F9Web\ApiResponseHelpers;

class StatusController extends Controller
{
    use ApiResponseHelpers;

    public function index()
    {
        $statuses = Status::all();

        return TaskStatusResource::collection($statuses);
    }

    public function store(TaskStatusRequest $request): \Illuminate\Http\JsonResponse
    {
        $status = Status::create($request->validated());

        return $this->respondCreated([
            'message' => 'Status created successfully',
            'status' => new TaskStatusResource($status),
        ]);
    }

    public function update(TaskStatusRequest $request, Status $status): \Illuminate\Http\JsonResponse
    {
        $status->update($request->validated());

        return $this->respondWithSuccess([
            'message' => 'Status updated successfully',
            'status' => new TaskStatusResource($status),
        ]);
    }

    public function destroy(Status $status): \Illuminate\Http\JsonResponse
    {
        $status->delete();

        return $this->respondOk('Status deleted successfully');
    }
}
