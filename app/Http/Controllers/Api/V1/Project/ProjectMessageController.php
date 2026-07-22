<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Project\MessageRequest;
use App\Models\Message;
use App\Models\Project;
use App\Services\Project\MessageService;
use Illuminate\Http\JsonResponse;

final class ProjectMessageController extends ApiController
{
    public function store(Project $project, MessageRequest $request, MessageService $messageService): JsonResponse
    {
        return $this->respondWithMessage($messageService->send($project, $request->toDto()));
    }

    public function destroy(Project $project, Message $message, MessageService $messageService): JsonResponse
    {
        $this->authorize('manage', $project);

        $messageService->deleteScheduledMessage($message);

        return $this->respondWithMessage('Scheduled message deleted successfully.');
    }
}
