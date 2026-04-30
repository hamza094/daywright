<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\User
 */
class AuthenticatedUserResource extends JsonResource
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
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'timezone' => $this->timezone ?? config('app.timezone', 'UTC'),
            'isAdmin' => $this->isAdmin(),
            'twoFactorEnabled' => $this->hasTwoFactorEnabled(),
            'avatar' => $this->when($this->avatar, fn () => $this->avatar_path),
            'verified' => (bool) $this->email_verified_at,
        ];
    }
}
