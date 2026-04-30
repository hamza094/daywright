<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\User;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;
use Timezone;

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
            'admin_granted_at' => $this->whenNotNull($this->formatDateInUserTimezone($this->admin_granted_at)),
            'admin_granted_by' => $this->adminGrantedBy?->name,
            'admin_revoked_at' => $this->whenNotNull($this->formatDateInUserTimezone($this->admin_revoked_at)),
            'admin_revoked_by' => $this->adminRevokedBy?->name,
            'is_subscribed' => $this->isSubscribed() ? 'Subscribed' : 'Not Subscribed',
            'created_at' => $this->created_at->diffForHumans(),
            'projects_count' => $this->whenCounted('projects'),
            'projects_member' => $this->projects_member_count ?? 0,
            'timezone' => $this->timezone ?? config('app.timezone', 'UTC'),
        ];
    }

    private function formatDateInUserTimezone(?Carbon $date): ?string
    {
        if (! $date instanceof Carbon) {
            return null;
        }

        return Timezone::convertToLocal(Carbon::parse($date));
    }
}
