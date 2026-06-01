<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\Admin\User\AdminUserSummaryResource;
use App\Http\Resources\Api\V1\ApiResourceLink;
use App\Http\Resources\Api\V1\StageResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Override;

/**
 * @mixin \App\Models\Project
 */
class ProjectResource extends JsonResource
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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'about' => Str::limit($this->about, 50),
            'slug' => $this->slug,
            'state' => $this->state(),
            'stage' => $this->whenLoaded(
                'stage',
                fn (): StageResource => new StageResource($this->stage),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'owner' => $this->whenLoaded(
                'user',
                fn (): AdminUserSummaryResource => new AdminUserSummaryResource($this->user),
            ),
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
