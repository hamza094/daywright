<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Override;

/**
 * @mixin \App\Models\User
 */
class InvitedUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|JsonSerializable
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            /**
             * User Uuid
             *
             *  @example 9b8ea076-6d80-4076-8a01-73b94f4c0bc3
             * */
            'uuid' => $this->uuid,

            /**
             *  @example Berry Allen
             * */
            'name' => $this->name,

            /**
             * User's username
             *
             *  @example berry
             * */
            'username' => $this->username,

            /**
             *  @example user@example.com
             * */
            'email' => $this->email,

            /**
             * User's avatar path
             *
             * @example 'https://eu.ui-avatars.com/api/?name=Prof. Jonas Miller DVM'
             * */
            'avatar' => $this->when(! empty($this->avatar_path), fn () => $this->avatar_path),

            'invitation_sent_at' => $this->when(
                $request->routeIs('project.pending.invitation') && $this->pivot,
                fn (): string => Carbon::parse($this->pivot->created_at)->format('M j, Y \a\t g:i A')
            ),

            /**
             * Links related to the user.
             *
             * @example {
             *   "self": "/api/v1/users/9b8ea076-6d80-4076-8a01-73b94f4c0bc3"
             * }
             */
            'links' => [
                'self' => '/api/v1/users/'.$this->uuid,
            ],

        ];
    }
}
