<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\MessageRequest;
use App\Models\Message;
use App\Models\Project;
use App\Services\Api\V1\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;

final class ProjectMessageController extends ApiController
{
    public function store(Project $project, MessageRequest $request, MessageService $messageService): JsonResponse
    {
        return response()->json([
            'message' => $messageService->send($project, $request->validated()),
        ]);
    }

    public function destroy(Project $project, Message $message, MessageService $messageService): HttpResponse
    {
        if ((int) $message->project_id !== (int) $project->id) {
            abort(404);
        }

        $messageService->deleteScheduledMessage($message);

        return response()->noContent();
    }
}
