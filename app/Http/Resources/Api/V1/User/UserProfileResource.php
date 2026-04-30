<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\User
 */
class UserProfileResource extends JsonResource
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
            'avatar' => $this->when($this->avatar, fn () => $this->avatar_path),
            'timezone' => $this->timezone ?? config('app.timezone', 'UTC'),
            'email' => $this->email,
            'verified' => $this->when(
                $request->user()?->is($this->resource),
                fn () => $this->email_verified_at?->diffForHumans(),
            ),
            'info' => new UserInfoResource($this->info),
            'created_at' => $this->created_at->diffForHumans(),
            'updated_at' => $this->updated_at->diffForHumans(),
        ];
    }
}
