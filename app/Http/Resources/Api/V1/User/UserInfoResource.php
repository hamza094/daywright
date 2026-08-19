<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\User;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\UserInfo
 */
#[SchemaName('UserProfileInfo')]
class UserInfoResource extends JsonResource
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
             * Optional mobile number as string to preserve leading zeros.
             *
             * @var string
             *
             * @example "0123456789"
             */
            'mobile' => $this->mobile,
            /**
             * Optional company name.
             *
             * @example Acme Inc.
             */
            'company' => $this->company,
            /**
             * Optional job title.
             *
             * @example Software Engineer
             */
            'position' => $this->position,
            /**
             * Optional biography.
             *
             * @example Product designer and async collaboration enthusiast.
             */
            'bio' => $this->bio,
            /**
             * Optional address.
             *
             * @example 123 Main St, City, Country
             */
            'address' => $this->address,
        ];
    }
}
