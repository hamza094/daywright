<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\TaskStatus as TaskStatusEnum;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Override;
use Timezone;

/**
 * @mixin \App\Models\Task
 */
class UserTasksResource extends JsonResource
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
            'status' => new TaskStatusResource($this->whenLoaded('status')),
            'project' => new ProjectsResource($this->whenLoaded('project')),
            'assignee' => UsersResource::collection($this->whenLoaded('assignee')),
            'due_at' => $this->when($this->due_at, fn () => Timezone::convertToLocal(Carbon::parse($this->due_at))),
            'is_overdue' => $this->due_at !== null
                && $this->due_at->isPast()
                && $this->status_id !== TaskStatusEnum::COMPLETED,
            'state' => $this->when($this->user_id !== auth()->id(), 'assigned', 'created'),
            'created_at' => $this->created_at->diffForHumans(),
        ];
    }
}
