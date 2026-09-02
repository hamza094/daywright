<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\User\InvitedUserResource;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\Conversation
 *
 * @property-read string|null $file_url
 */
#[SchemaName('ProjectConversation')]
class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray($request): array
    {
        $fileUrl = $this->file_url;

        return [
            /**
             * Conversation identifier.
             *
             * @example 44
             */
            'id' => $this->id,

            /**
             * Conversation message body when the message contains text.
             *
             * @example Can someone review the latest copy draft?
             */
            'message' => $this->whenNotNull($this->message),

            /**
             * Public URL for the uploaded attachment when the conversation contains a file.
             */
            'file' => $this->when((bool) $fileUrl, fn () => $fileUrl),

            /**
             * User who sent the conversation message.
             */
            'user' => new InvitedUserResource($this->whenLoaded('user')),

            /**
             * Conversation creation timestamp in UTC ISO 8601 format.
             *
             * @format date-time
             *
             * @example 2025-08-15T09:00:00+00:00
             */
            'created_at' => $this->created_at?->toIso8601String(),

            /**
             * Route links related to the conversation.
             */
            'links' => $this->whenLoaded('project', fn (): array => [
                'project' => ApiResourceLink::project($this->project),
            ]),
        ];
    }

    /*private function formatMentions(?string $message): ?string
  {
    if (!$message) {
        return null;
    }

    return preg_replace(
        '/@([a-zA-Z][\w.-]*)/', // Match usernames
        '<a href="/user/$1/profile" target="_blank">@$1</a>', // Replace with hyperlink
        e($message) // Escape HTML to prevent XSS
    );
  }*/
}
