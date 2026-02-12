<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class UsersResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'isSubscribed' => $this->isSubscribed() ? 'Subscribed' : 'Not Subscribed',
            'created_at' => $this->created_at->diffForHumans(),
            'projects_count' => $this->whenCounted('projects'),
            'projects_member' => $this->members(true)->count(),
            'timezone' => $this->timezone ?? config('app.timezone', 'UTC'),
        ];
    }
}
