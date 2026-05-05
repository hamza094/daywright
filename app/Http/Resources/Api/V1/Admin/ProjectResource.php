<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\ApiResourceLink;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Override;

class ProjectResource extends JsonResource
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
            'name' => $this->name,
            'about' => str_limit($this->about, 50),
            'slug' => $this->slug,
            'state' => $this->state(),
            'stage' => $this->whenLoaded('stage'),
            'created_at' => $this->created_at?->toIso8601String(),
            'owner' => $this->whenLoaded('user'),
            'tasks_count' => $this->whenCounted('tasks'),
            'members_count' => $this->whenCounted('activeMembers'),
            'score' => $this->health_score,
            'status' => $this->health_status,
            'health_score_calculated_at' => $this->health_score_calculated_at?->toIso8601String(),
            'links' => [
                'self' => ApiResourceLink::project($this->resource),
            ],
        ];
    }
}
