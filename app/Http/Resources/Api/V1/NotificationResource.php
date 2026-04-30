<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\User\InvitedUserResource;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Override;

class NotificationResource extends JsonResource
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
            'id' => $this->id,
            'type' => class_basename($this->type),
            'message' => $this->data['message'],
            'link' => $this->data['link'],
            'notifier' => new InvitedUserResource((object) ($this->data['notifier'] ?? [])),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at->diffForHumans(),
        ];
    }
}
