<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\Admin\User\AdminUserSummaryResource;
use App\Http\Resources\Api\V1\Task\TaskStatusResource;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Override;

class TaskResource extends JsonResource
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
            'title' => $this->title,
            'description' => str_limit($this->description, 50),
            'status_id' => $this->status_id,
            'status' => new TaskStatusResource($this->whenLoaded('status')),
            'project' => new TaskProjectResource($this->whenLoaded('project')),
            'members' => AdminUserSummaryResource::collection($this->whenLoaded('assignee')),

            'notified' => $this->notified,
            'owner' => new AdminUserSummaryResource($this->whenLoaded('owner')),
            'due_at' => $this->when($this->due_at, fn (): string => $this->due_at->toIso8601String()),
            'state' => $this->state(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
