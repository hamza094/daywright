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
#[SchemaName('UserSummary')]
class UserSummaryResource extends JsonResource
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
        ];
    }
}
