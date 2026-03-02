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
    private const string DATETIME_FORMAT = 'Y-m-d H:i:s';

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
             * Last time the token was used (Y-m-d H:i:s), or null if never used
             *
             * @example "2025-07-08 12:34:56"
             */
            'last_used_at' => $this->last_used_at ? $this->last_used_at->format(self::DATETIME_FORMAT) : null,

            /**
             * Token creation date (Y-m-d H:i:s)
             *
             * @example "2025-07-01 09:00:00"
             */
            'created_at' => $this->created_at ? $this->created_at->format(self::DATETIME_FORMAT) : null,

            /**
             * Token expiration date (Y-m-d H:i:s), or null if does not expire
             *
             * @example "2025-12-31 23:59:59"
             */
            'expires_at' => $this->expires_at ? $this->expires_at->format(self::DATETIME_FORMAT) : null,
        ];
    }
}
