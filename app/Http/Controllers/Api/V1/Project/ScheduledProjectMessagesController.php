<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Services\Project\MessageService;
use Illuminate\Http\JsonResponse;

final class ScheduledProjectMessagesController extends ApiController
{
    public function __invoke(Project $project, MessageService $messageService): JsonResponse
    {
        return $this->respondWithData($messageService->scheduledMessages($project));
    }
}
