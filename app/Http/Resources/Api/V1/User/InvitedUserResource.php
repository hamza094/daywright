<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\User;

use App\Http\Resources\Api\V1\ApiResourceLink;
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
            'uuid' => $this->uuid,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'avatar' => $this->when(! empty($this->avatar_path), fn () => $this->avatar_path),
            'invitation_sent_at' => $this->when(
                $request->routeIs('api.v1.project.pending.invitation') && $this->pivot,
                fn (): string => $this->pivot->created_at->toIso8601String()
            ),
            'links' => [
                'self' => ApiResourceLink::user($this->resource),
            ],
        ];
    }
}
