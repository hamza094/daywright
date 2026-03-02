<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\UserInfo
 */
class UserInfoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray($request)
    {
        return [
            /**
             * User's mobile phone number
             *
             * @example 1234567890
             */
            'mobile' => $this->mobile,

            /**
             * Company the user is associated with
             *
             * @example Acme Inc.
             */
            'company' => $this->company,

            /**
             * User's job position or title
             *
             * @example Developer
             */
            'position' => $this->position,

            /**
             * User's biography or profile description
             *
             * @example A short bio.
             */
            'bio' => $this->bio,

            /**
             * User's address
             *
             * @example 123 Main St, City, Country
             */
            'address' => $this->address,
        ];
    }
}
