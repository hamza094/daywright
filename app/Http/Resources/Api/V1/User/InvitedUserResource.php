<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\User;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\User
 */
class InvitedUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'avatar' => $this->when(! empty($this->avatar_path), fn () => $this->avatar_path),
            'invitation_sent_at' => $this->when(
                $request->routeIs('project.pending.invitation') && $this->pivot,
                fn (): string => Carbon::parse($this->pivot->created_at)->format('M j, Y \\a\\t g:i A')
            ),
            'links' => [
                'self' => '/api/v1/users/'.$this->uuid,
            ],
        ];
    }
}
