<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\DataTransferObjects\Notification\NotificationActorData;
use App\DataTransferObjects\Project\CreateConversationData;
use App\Enums\FileType;
use App\Events\DeleteConversation;
use App\Events\NewMessage;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use App\Notifications\UserMentioned;
use App\Services\FileService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Safe\parse_url;

class ConversationService
{
    private const array CONVERSATION_RESOURCE_RELATIONS = ['user', 'project:id,slug'];

    /**
     * Service For File Storage
     *
     * App\Service\Api\V1\FileService
     */
    public function __construct(private readonly FileService $fileService) {}

    /**
     * Stores a new conversation and dispatches events and notifications.
     */
    public function storeConversation(Project $project, User $actor, CreateConversationData $payload, ?UploadedFile $file = null): ?Conversation
    {
        try {
            $data = $this->prepareConversationData($payload, $project, $file);

            $conversation = $this->loadForResponse(
                $this->createConversation($project, $actor, $data)
            );

            NewMessage::dispatch($conversation, $project->slug);

            $this->userMentioned($conversation, $project, $actor);

            return $conversation;
        } catch (Exception $e) {
            Log::error('Error storing conversation', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function userMentioned(Conversation $conversation, Project $project, User $actor): void
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
                    NotificationActorData::fromUser($actor))
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

    public function loadForResponse(Conversation $conversation): Conversation
    {
        $conversation->loadMissing(self::CONVERSATION_RESOURCE_RELATIONS);

        return $conversation;
    }

    /**
     * Prepares the data required to create a conversation.
     */
    private function prepareConversationData(CreateConversationData $payload, Project $project, ?UploadedFile $file = null): CreateConversationData
    {
        if ($file instanceof UploadedFile) {
            return $payload->withStoredFile($this->fileService->store(
                $project->id,
                $file,
                FileType::CONVERSATION
            ));
        }

        return $payload;
    }

    private function createConversation(Project $project, User $actor, CreateConversationData $data): Conversation
    {
        return $project->conversations()->create(array_merge($data->toArray(), [
            'user_id' => $actor->id,
        ]));
    }

    private function deleteFileIfExists(?string $filePath): void
    {
        if (! $filePath) {
            return;
        }

        $path = $filePath;

        if (str_starts_with($filePath, 'http')) {
            $parsedPath = (string) (parse_url($filePath, PHP_URL_PATH) ?: '');
            $path = ltrim($parsedPath, '/');
        }

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
