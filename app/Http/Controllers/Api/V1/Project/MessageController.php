<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\MessageRequest;
use App\Models\Message;
use App\Models\Project;
use App\Services\Project\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;

class MessageController extends ApiController
{
    public function store(Project $project, MessageRequest $request, MessageService $messageService): JsonResponse
    {
        $responseMessage = $messageService->send($project, $request->validated());

        return $this->respondWithMessage($responseMessage);
    }

    public function scheduled(Project $project, MessageService $messageService): Collection
    {
        return $messageService->scheduledMessages($project);
    }

    public function destroy(Project $project, Message $message, MessageService $messageService): HttpResponse
    {
        $messageService->deleteScheduledMessage($message);

        return $this->respondNoContent();
    }
}
