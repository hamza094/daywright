<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Project\ScheduledProjectMessagesIndexRequest;
use App\Http\Resources\Api\V1\Project\ScheduledMessageResource;
use App\Models\Project;
use App\Services\Project\MessageService;
use Illuminate\Http\JsonResponse;

final class ScheduledProjectMessagesController extends ApiController
{
    public function __invoke(Project $project, ScheduledProjectMessagesIndexRequest $request, MessageService $messageService): JsonResponse
    {
        return ScheduledMessageResource::collection(
            $messageService->paginateScheduledMessages($project, $request->perPage(), $request->pageNumber())
        )->response();
    }
}
