<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\ConversationRequest;
use App\Http\Resources\Api\V1\ConversationResource;
use App\Models\Conversation;
use App\Models\Project;
use App\Repository\Api\V1\ConversationRepository;
use App\Services\Project\ConversationService;
use Illuminate\Http\JsonResponse;

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

        return $this->respondCreated(new ConversationResource($conversation));
    }

    public function destroy(Project $project, Conversation $conversation): JsonResponse
    {
        $this->authorize('delete', $conversation);

        $this->conversationService->deleteConversation($conversation, $project);

        return $this->respondWithMessage('Conversation deleted successfully.');
    }
}
