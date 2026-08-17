<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Project\ConversationIndexRequest;
use App\Http\Requests\Api\V1\Project\ConversationRequest;
use App\Http\Resources\Api\V1\ConversationResource;
use App\Models\Conversation;
use App\Models\Project;
use App\Repository\Api\V1\ConversationRepository;
use App\Services\Project\ConversationService;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;

class ConversationController extends ApiController
{
    public function __construct(private readonly ConversationService $conversationService) {}

    /**
     * List project conversations.
     *
     * Returns a paginated conversation feed for the specified project.
     */
    #[Endpoint(operationId: 'conversations.list')]
    public function index(Project $project, ConversationIndexRequest $request, ConversationRepository $repository): JsonResponse
    {
        return ConversationResource::collection(
            $repository->getProjectConversations($project, $request->perPage())
        )->response();
    }

    /**
     * Create a project conversation.
     *
     * Creates a new project conversation with a message body, an attachment, or both.
     */
    #[Endpoint(operationId: 'conversations.create')]
    public function store(Project $project, ConversationRequest $request): JsonResponse
    {
        $conversation = $this->conversationService->storeConversation(
            $project,
            $this->authenticatedUser(),
            $request->toDto(),
        );

        return $this->respondCreated(new ConversationResource($conversation));
    }

    /**
     * Delete a project conversation.
     *
     * Permanently removes a conversation that the authenticated user is allowed to delete.
     */
    #[Endpoint(operationId: 'conversations.destroy')]
    public function destroy(Project $project, Conversation $conversation): JsonResponse
    {
        $this->conversationService->deleteConversation($conversation, $project);

        return $this->respondWithMessage('Conversation deleted successfully.');
    }
}
