<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\TaskStatusResource;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Override;
use Timezone;

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
            'members' => UserResource::collection($this->whenLoaded('assignee')),

            'due_at_utc' => $this->due_at,
            'notified' => $this->notified,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'due_at' => $this->when($this->due_at, fn () => Timezone::convertToLocal(Carbon::parse($this->due_at))),
            'state' => $this->state(),
            'created_at' => $this->created_at->diffForHumans([
                'parts' => 3,
                'short' => true,
            ]),
            'updated_at' => $this->updated_at->diffForHumans([
                'parts' => 2,
            ]),
        ];
    }
}
