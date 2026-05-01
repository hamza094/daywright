<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\User;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\User
 */
class AdminUserResource extends JsonResource
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
            'avatar' => $this->avatar,
            'is_admin' => $this->isAdmin(),
            'admin_granted_at' => $this->whenNotNull($this->serializeDate($this->admin_granted_at)),
            'admin_granted_by' => $this->adminGrantedBy?->name,
            'admin_revoked_at' => $this->whenNotNull($this->serializeDate($this->admin_revoked_at)),
            'admin_revoked_by' => $this->adminRevokedBy?->name,
            'is_subscribed' => $this->isSubscribed() ? 'Subscribed' : 'Not Subscribed',
            'created_at' => $this->created_at?->toIso8601String(),
            'projects_count' => $this->whenCounted('projects'),
            'projects_member' => $this->projects_member_count ?? 0,
            'timezone' => $this->timezone ?? config('app.timezone', 'UTC'),
        ];
    }

    private function serializeDate(?CarbonInterface $date): ?string
    {
        if (! $date instanceof CarbonInterface) {
            return null;
        }

        return $date->toIso8601String();
    }
}
