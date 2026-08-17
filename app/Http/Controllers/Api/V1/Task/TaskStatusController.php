<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Enums\TaskDueNotifies;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Task\TaskStatusIndexResource;
use App\Models\TaskStatus;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;

class TaskStatusController extends ApiController
{
    /**
     * List task statuses and due-notification options.
     *
     * Returns the released task status reference data used by public task workflows.
     */
    #[Endpoint(operationId: 'taskStatuses.list')]
    public function __invoke(): JsonResponse
    {
        return $this->respondWithData(
            new TaskStatusIndexResource(TaskStatus::all(), TaskDueNotifies::values()),
        );
    }
}
