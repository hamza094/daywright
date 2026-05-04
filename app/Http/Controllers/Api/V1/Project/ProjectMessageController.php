<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\MessageRequest;
use App\Models\Message;
use App\Models\Project;
use App\Services\Api\V1\MessageService;
use Illuminate\Http\JsonResponse;

final class ProjectMessageController extends ApiController
{
    public function store(Project $project, MessageRequest $request, MessageService $messageService): JsonResponse
    {
        return $this->respondWithMessage($messageService->send($project, $request->validated()));
    }

    public function destroy(Project $project, Message $message, MessageService $messageService): JsonResponse
    {
        if ((int) $message->project_id !== (int) $project->id) {
            abort(404);
        }

        $messageService->deleteScheduledMessage($message);

        return $this->respondWithMessage('Scheduled message deleted successfully.');
    }
}
