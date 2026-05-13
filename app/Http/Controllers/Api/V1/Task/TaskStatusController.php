<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Enums\TaskDueNotifies;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Task\TaskStatusIndexResource;
use App\Models\TaskStatus;
use Illuminate\Http\JsonResponse;

class TaskStatusController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        return $this->respondWithData(
            new TaskStatusIndexResource(TaskStatus::all(), TaskDueNotifies::values()),
        );
    }
}
