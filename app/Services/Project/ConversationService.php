<?php

declare(strict_types=1);

namespace App\Services\Project;

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
    /**
     * Service For File Storage
     *
     * App\Service\Api\V1\FileService
     */
    public function __construct(private readonly FileService $fileService) {}

    /**
     * Stores a new conversation and dispatches events and notifications.
     *
     * @param  array<string, mixed>  $payload
     */
    public function storeConversation(Project $project, User $actor, array $payload, ?UploadedFile $file = null): ?Conversation
    {
        try {
            $data = $this->prepareConversationData($payload, $project, $file);

            $conversation = $this->createConversation($project, $actor, $data);

            $conversation->load(['user', 'project:id,slug']);

            NewMessage::dispatch($conversation, $project->slug);

            $this->userMentioned($conversation, $project, $actor);

            return $conversation;
        } catch (Exception $e) {
            Log::error('Error storing conversation', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
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
                    $actor->getNotifierData())
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
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function prepareConversationData(array $payload, Project $project, ?UploadedFile $file = null): array
    {
        $data = [];

        if (array_key_exists('message', $payload)) {
            $data['message'] = $payload['message'];
        }

        if ($file instanceof UploadedFile) {
            $data['file'] = $this->fileService->store(
                $project->id,
                $file,
                FileType::CONVERSATION
            );
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createConversation(Project $project, User $actor, array $data): Conversation
    {
        return $project->conversations()->create(array_merge($data, [
            'user_id' => $actor->id,
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
