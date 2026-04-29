<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\MessageRequest;
use App\Models\Message;
use App\Models\Project;
use App\Services\Api\V1\MessageService;
use F9Web\ApiResponseHelpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class MessageController extends ApiController
{
    use ApiResponseHelpers;

    public function message(Project $project, MessageRequest $request, MessageService $messageService): JsonResponse
    {
        $responseMessage = $messageService->send($project, $request->validated());

        return response()->json(['message' => $responseMessage], 200);
    }

    public function scheduled(Project $project, MessageService $messageService): JsonResponse|Collection
    {
        $scheduledMessages = $messageService->scheduledMessages($project);

        if ($scheduledMessages->isEmpty()) {

            return $this->respondNoContent([
                'message' => 'No project schedule messages found',
            ]);

        }

        return $scheduledMessages;
    }

    public function delete(Project $project, Message $message, MessageService $messageService): JsonResponse
    {
        $messageService->deleteScheduledMessage($message);

        return $this->respondNoContent([
            'message' => 'Scheduled message deleted Successfully',
        ]);
    }
}
