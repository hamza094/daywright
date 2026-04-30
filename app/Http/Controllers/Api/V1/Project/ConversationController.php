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

class ConversationController extends ApiController
{
    /**
     * Service For Conversation Feature
     *
     * App\Service\Api\V1\ConversationService
     */
    public function __construct(private readonly ConversationService $conversationService) {}

    public function index(Project $project, ConversationRepository $repository): JsonResponse
    {
        $this->authorize('access', $project);

        $conversations = $repository->getProjectConversations($project);

        return response()->json([
            'data' => $conversations,
        ]);
    }

    /**
     * Store project group conversation.
     */
    public function store(Project $project,
        ConversationRequest $request): JsonResponse
    {
        $this->authorize('access', $project);

        $conversation = $this->conversationService->storeConversation($request, $project);

        return response()->json([
            'message' => 'New Conversation added Successfully',
            'conversation' => new ConversationResource($conversation),
        ], 201);
    }

    public function destroy(Project $project, Conversation $conversation): JsonResponse
    {
        $this->authorize('delete', $conversation);

        $this->conversationService->deleteConversation($conversation, $project);

        return response()->json([
            'message' => 'Project Conversation deleted successfully',
            'path' => $project->path(),
        ], 204);
    }
}
