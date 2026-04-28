<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskDueNotifies;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\TaskStatusResource;
use App\Models\TaskStatus;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TaskStatusController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'statuses' => TaskStatusResource::collection(TaskStatus::all()),
            'due_notifies' => TaskDueNotifies::values(),
        ], Response::HTTP_OK);
    }
}
