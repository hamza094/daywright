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
#[SchemaName('UserProfile')]
class UserProfileResource extends JsonResource
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
             * Internal numeric user identifier.
             *
             * @example 1
             */
            'id' => $this->id,
            /**
             * Stable public UUID for the user.
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
             * @example https://daywright.test/storage/avatars/berry.png
             */
            'avatar' => $this->when($this->avatar, fn () => $this->avatar_path),
            /**
             * IANA timezone identifier (non-null string).
             *
             * @example UTC
             */
            'timezone' => $this->timezone ?? config('app.timezone', 'UTC'),
            /**
             * Primary email address.
             *
             * @example berry@example.com
             */
            'email' => $this->email,
            /**
             * Email verification timestamp for the authenticated owner, in UTC ISO 8601 format.
             * Null if email is not verified.
             *
             * @example 2025-07-01T09:00:00+00:00
             */
            'verified' => $this->when(
                $request->user()?->is($this->resource),
                fn () => $this->email_verified_at?->toIso8601String(),
            ),
            /**
             * Extended profile information.
             */
            'info' => new UserInfoResource($this->info),
            /**
             * Profile creation timestamp in UTC ISO 8601 format.
             *
             * @example 2025-07-01T09:00:00+00:00
             */
            'created_at' => $this->created_at?->toIso8601String(),
            /**
             * Profile update timestamp in UTC ISO 8601 format.
             *
             * @example 2025-07-08T12:34:56+00:00
             */
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
