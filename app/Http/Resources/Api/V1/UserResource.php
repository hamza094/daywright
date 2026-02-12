<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,

            /**
             * User UUID
             *
             * @example 9b8ea076-6d80-4076-8a01-73b94f4c0bc3
             */
            'uuid' => $this->uuid,

            /**
             * User's full name
             *
             * @example Berry
             */
            'name' => $this->name,

            /**
             * Username (if set)
             *
             * @example berry123
             */
            'username' => $this->username,

            /**
             * User's avatar URL (if exists)
             *
             * @example https://eu.ui-avatars.com/api/?name=Berry
             */
            'avatar' => $this->when($this->avatar, fn () => $this->avatar_path),

            /**
             * User's timezone
             *
             * @example Asia/Karachi
             */
            'timezone' => $this->timezone ?? config('app.timezone', 'UTC'),

            /**
             * User email address
             *
             * @example user@example.com
             */
            'email' => $this->email,

            /**
             * Email verification status (human readable, only for self)
             *
             * @example 2 hours ago
             */
            'verified' => $this->when($this->id === auth()->id(), fn () => $this->email_verified_at?->diffForHumans()),

            /**
             * Additional user info (profile details)
             */
            'info' => new UserInfoResource($this->info),

            /**
             * Account creation date (human readable)
             *
             * @example 1 month ago
             */
            'created_at' => $this->created_at->diffForHumans(),

            /**
             * Last update date (human readable)
             *
             * @example 2 days ago
             */
            'updated_at' => $this->updated_at->diffForHumans(),
        ];
    }
}
