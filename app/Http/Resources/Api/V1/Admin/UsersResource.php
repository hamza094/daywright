<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Override;
use Timezone;

class UsersResource extends JsonResource
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
            'uuid' => $this->uuid,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'isAdmin' => $this->isAdmin(),
            'admin_granted_at' => $this->whenNotNull($this->formatDateInUserTimezone($this->admin_granted_at)),
            'admin_granted_by' => $this->adminGrantedBy?->name,
            'admin_revoked_at' => $this->whenNotNull($this->formatDateInUserTimezone($this->admin_revoked_at)),
            'admin_revoked_by' => $this->adminRevokedBy?->name,
            'isSubscribed' => $this->isSubscribed() ? 'Subscribed' : 'Not Subscribed',
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
