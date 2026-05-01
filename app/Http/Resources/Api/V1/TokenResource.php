<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \Laravel\Sanctum\PersonalAccessToken
 */
class TokenResource extends JsonResource
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
        return [
            /**
             * Token ID
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Token name/label
             *
             * @example "My API Token"
             */
            'name' => $this->name,

            /**
             * Token abilities (scopes)
             *
             * @example ["read", "write"]
             */
            'abilities' => $this->abilities,

            /**
             * Last time the token was used in UTC ISO 8601 format, or null if never used.
             *
             * @example "2025-07-08T12:34:56+00:00"
             */
            'last_used_at' => $this->last_used_at?->toIso8601String(),

            /**
             * Token creation timestamp in UTC ISO 8601 format.
             *
             * @example "2025-07-01T09:00:00+00:00"
             */
            'created_at' => $this->created_at?->toIso8601String(),

            /**
             * Token expiration timestamp in UTC ISO 8601 format, or null if it does not expire.
             *
             * @example "2025-12-31T23:59:59+00:00"
             */
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
