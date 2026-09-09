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
#[SchemaName('PublicUserProfile')]
class PublicUserProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * This resource is used for the /users/{user} endpoint and shows information
     * appropriate for collaborators (shared project/team members) and the profile owner.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            /**
             * Internal numeric user identifier.
             *
             * @example 1
             */
            'id' => $this->id,
            /**
             * Stable public UUID for the user.
             *
             * @format uuid
             *
             * @example 9c4cc6f1-11e0-4c42-8f29-2d3d6d3d7412
             */
            'uuid' => $this->uuid,
            /**
             * Display name shown across the application.
             *
             * @example Berry
             */
            'name' => $this->name,
            /**
             * Public username.
             *
             * @example berry
             */
            'username' => $this->username,
            /**
             * Avatar URL when present.
             *
             * @format uri
             *
             * @example https://daywright.test/storage/avatars/berry.png
             */
            'avatar' => $this->when($this->avatar, fn () => $this->avatar_path),
            /**
             * IANA timezone identifier (non-null string).
             *
             * @var string
             *
             * @example UTC
             */
            'timezone' => $this->timezone ?? config('app.timezone', 'UTC'),
            /**
             * Primary email address.
             *
             * @format email
             *
             * @example berry@example.com
             */
            'email' => $this->email,
            /**
             * Extended profile information (mobile, address, company, position, bio).
             */
            'info' => new UserInfoResource($this->info),
            /**
             * Profile creation timestamp in UTC ISO 8601 format.
             *
             * @format date-time
             *
             * @example 2025-07-01T09:00:00+00:00
             */
            'created_at' => $this->created_at?->toIso8601String(),
            /**
             * Profile update timestamp in UTC ISO 8601 format.
             *
             * @format date-time
             *
             * @example 2025-07-08T12:34:56+00:00
             */
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
