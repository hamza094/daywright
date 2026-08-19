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
#[SchemaName('AuthenticatedUser')]
class AuthenticatedUserResource extends JsonResource
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
             * Primary email address used for login and notifications.
             *
             * @format email
             *
             * @example berry@example.com
             */
            'email' => $this->email,
            /**
             * IANA timezone identifier.
             *
             * @example UTC
             */
            'timezone' => $this->timezone ?? config('app.timezone', 'UTC'),
            /**
             * Indicates whether the authenticated user has admin access.
             *
             * @example false
             */
            'is_admin' => $this->isAdmin(),
            /**
             * Indicates whether two-factor authentication is enabled.
             *
             * @example false
             */
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            /**
             * Public avatar URL when an avatar is present.
             *
             * @format uri
             *
             * @example https://daywright.test/storage/avatars/berry.png
             */
            'avatar' => $this->when($this->avatar, fn () => $this->avatar_path),
            /**
             * Indicates whether the email address has been verified.
             *
             * @example true
             */
            'verified' => (bool) $this->email_verified_at,
        ];
    }
}
