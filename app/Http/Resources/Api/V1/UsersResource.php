<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\User
 */
class UsersResource extends JsonResource
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
        if ($request->is('api/v1/dashboard/tasks')) {
            return [
                'uuid' => $this->uuid,
                'name' => $this->name,
            ];
        }

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
             *  @example user@example.com
             * */
            'email' => $this->email,

            /**
             *  @example Asia/Karachi
             * */
            'timezone' => $this->timezone ?? config('app.timezone', 'UTC'),

            /**
             * Indicates whether the user is an admin.
             * */
            'isAdmin' => $this->isAdmin(),

            /**
             * Indicates whether two-factor authentication is enabled.
             */
            'twoFactorEnabled' => $this->hasTwoFactorEnabled(),

            /**
             * User's avatar URL (if exists).
             *
             * @example https://eu.ui-avatars.com/api/?name=Berry
             */
            'avatar' => $this->when($this->avatar,
                fn () => $this->avatar_path),

            /**
             * Return user email verified or not
             */
            'verified' => (bool) $this->email_verified_at,

        ];
    }
}
