<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\User;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\User
 */
#[SchemaName('PendingInvitationUser')]
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
            /**
             * Stable public UUID for the invited user.
             *
             * @format uuid
             *
             * @example 9c4cc6f1-11e0-4c42-8f29-2d3d6d3d7412
             */
            'uuid' => $this->uuid,
            /**
             * Invited user display name.
             *
             * @example Berry
             */
            'name' => $this->name,
            /**
             * Invited user username.
             *
             * @example berry
             */
            'username' => $this->username,
            /**
             * Invited user email address.
             *
             * @format email
             *
             * @example berry@example.com
             */
            'email' => $this->email,
            /**
             * Invited user avatar URL when present.
             *
             * @format uri
             */
            'avatar' => $this->when(! empty($this->avatar_path), fn () => $this->avatar_path),
            /**
             * Invitation creation timestamp in UTC ISO 8601 format for pending invitations.
             *
             * @example 2025-07-09T14:00:00+00:00
             */
            'invitation_sent_at' => $this->when(
                isset($this->resource->pivot) && isset($this->resource->pivot->created_at),
                fn (): string => $this->resource->pivot->created_at->toIso8601String()
            ),
            /**
             * Route links related to the invited user.
             */
            'links' => [
                'self' => route('api.v1.users.show', ['user' => $this->uuid], false),
            ],
        ];
    }
}
