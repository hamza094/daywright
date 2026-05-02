<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\ConversationRequest;
use App\Http\Resources\Api\V1\ConversationResource;
use App\Models\Conversation;
use App\Models\Project;
use App\Repository\Api\V1\ConversationRepository;
use App\Services\Api\V1\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;

class ConversationController extends ApiController
{
    public function __construct(private readonly ConversationService $conversationService) {}

    public function index(Project $project, ConversationRepository $repository): JsonResponse
    {
        $this->authorize('access', $project);

        return response()->json([
            'data' => $repository->getProjectConversations($project),
        ]);
    }

    public function store(Project $project, ConversationRequest $request): JsonResponse
    {
        $this->authorize('access', $project);

        $conversation = $this->conversationService->storeConversation($request, $project);

        return response()->json([
            'message' => 'New Conversation added Successfully',
            'conversation' => new ConversationResource($conversation),
            'path' => $project->path(),
        ]);
    }

    public function destroy(Project $project, Conversation $conversation): HttpResponse
    {
        $this->authorize('delete', $conversation);

        $this->conversationService->deleteConversation($conversation, $project);

        return response()->noContent();
    }
}
