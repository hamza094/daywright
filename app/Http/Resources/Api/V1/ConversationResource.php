<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\User\InvitedUserResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\Conversation
 *
 * @property-read string|null $file_url
 */
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
            'id' => $this->id,

            'message' => $this->whenNotNull($this->message),

            'file' => $this->when((bool) $fileUrl, fn () => $fileUrl),

            'user' => new InvitedUserResource($this->whenLoaded('user')),

            'created_at' => $this->created_at?->toIso8601String(),

            'links' => $this->whenLoaded('project', fn () => [
                'project' => $this->project->path(),
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
