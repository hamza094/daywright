<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Task;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Override;

/**
 * @mixin \App\Models\User
 */
#[SchemaName('TaskMember')]
class TaskMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|JsonSerializable
     */
    #[Override]
    public function toArray($request)
    {
        return [
            /**
             * User identifier.
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Stable public UUID for the member.
             *
             * @example 9b8ea076-6d80-4076-8a01-73b94f4c0bc3
             */
            'uuid' => $this->uuid,

            /**
             * Member display name.
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
             * Member email address.
             *
             * @example user@example.com
             */
            'email' => $this->email,

            /**
             * Member avatar URL when present.
             *
             * @example https://eu.ui-avatars.com/api/?name=Berry
             */
            'avatar' => $this->when($this->avatar,
                fn () => $this->avatar_path),

        ];
    }
}
