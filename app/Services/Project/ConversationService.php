<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\FileType;
use App\Events\DeleteConversation;
use App\Events\NewMessage;
use App\Http\Requests\Api\V1\ConversationRequest;
use App\Models\Conversation;
use App\Models\Project;
use App\Notifications\UserMentioned;
use App\Services\FileService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Safe\parse_url;

class ConversationService
{
    /**
     * Service For File Storage
     *
     * App\Service\Api\V1\FileService
     */
    public function __construct(private readonly FileService $fileService) {}

    /**
     * Stores a new conversation and dispatches events and notifications.
     */
    public function storeConversation(ConversationRequest $request, Project $project): ?Conversation
    {
        try {
            $data = $this->prepareConversationData($request, $project);

            $conversation = $this->createConversation($project, $data);

            $conversation->load(['user', 'project:id,slug']);

            // Fire the NewMessage event
            NewMessage::dispatch($conversation, $project->slug);

            $this->userMentioned($conversation, $project);

            return $conversation;

        } catch (Exception $e) {
            Log::error('Error storing conversation', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    public function userMentioned(Conversation $conversation, Project $project): void
    {
        if (! $conversation->message) {
            return;
        }

        $mentionedUsers = $conversation->mentionedUsersData();

        if ($mentionedUsers->isEmpty()) {
            return;
        }

        try {
            Notification::send($mentionedUsers,
                new UserMentioned(
                    $project->name,
                    $project->slug,
                    auth()->user()->getNotifierData())
            );

        } catch (Exception $e) {
            Log::error('Failed to send notifications', [
                'error' => $e->getMessage(),
                'users' => $mentionedUsers->pluck('uuid')->toArray(),
            ]);
        }
    }

    public function deleteConversation(Conversation $conversation, Project $project): void
    {
        DeleteConversation::dispatch($conversation->id, $project->slug);

        defer(fn () => $this->deleteFileIfExists($conversation->file));

        $conversation->delete();
    }

    /**
     * Prepares the data required to create a conversation.
     */
    private function prepareConversationData(ConversationRequest $request, Project $project): array
    {
        $data = [];

        if ($request->has('message')) {
            $data = $request->safe()->only(['message']);
        }

        // Check if a file is present in the request and process the upload
        if ($request->hasFile('file')) {
            $data['file'] = $this->fileService->store(
                $project->id,
                $request->file('file'),
                FileType::CONVERSATION
            );
        }

        return $data;
    }

    private function createConversation(Project $project, array $data): Conversation
    {
        return $project->conversations()->create(array_merge($data, [
            'user_id' => auth()->id(),
        ]));
    }

    private function deleteFileIfExists(?string $filePath): void
    {
        if (! $filePath) {
            return;
        }

        $path = str_starts_with($filePath, 'http')
            ? ltrim(parse_url($filePath, PHP_URL_PATH) ?: '', '/')
            : $filePath;

        if ($path === '') {
            return;
        }

        try {
            Storage::disk('s3')->delete($path);
        } catch (Exception $e) {
            Log::error('S3 file deletion error', ['file' => $filePath, 'error' => $e->getMessage()]);
        }
    }
}
