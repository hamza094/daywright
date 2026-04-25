<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Task;

use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Override;

/**
 * @mixin \App\Models\User
 */
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
             * User Uuid
             *
             *  @example 9b8ea076-6d80-4076-8a01-73b94f4c0bc3
             * */
            'uuid' => $this->uuid,

            /**
             *  @example berry
             * */
            'name' => $this->name,

            /**
             *  @example berry
             * */
            'username' => $this->username,

            /**
             *  @example user@example.com
             * */
            'email' => $this->email,

            /**
             * User's avatar URL (if exists).
             *
             * @example https://eu.ui-avatars.com/api/?name=Berry
             */
            'avatar' => $this->when($this->avatar,
                fn () => $this->avatar_path),

        ];
    }
}
