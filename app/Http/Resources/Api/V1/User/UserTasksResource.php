<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\User;

use App\Http\Resources\Api\V1\Project\ProjectSummaryResource;
use App\Http\Resources\Api\V1\Task\TaskStatusResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\Task
 */
class UserTasksResource extends JsonResource
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
            /**
             * Task identifier.
             *
             * @example 14
             */
            'id' => $this->id,
            /**
             * Task title shown in dashboard widgets.
             *
             * @example Finalize onboarding checklist
             */
            'title' => $this->title,
            /**
             * Current task status.
             */
            'status' => new TaskStatusResource($this->whenLoaded('status')),
            /**
             * Parent project summary.
             */
            'project' => new ProjectSummaryResource($this->whenLoaded('project')),
            /**
             * Assigned users for the task.
             */
            'assignee' => UserSummaryResource::collection($this->whenLoaded('assignee')),
            /**
             * Due date in UTC ISO 8601 format when the task has a deadline.
             *
             * @example 2025-08-20T10:30:00+00:00
             */
            'due_at' => $this->when($this->due_at !== null, fn (): string => $this->due_at->toIso8601String()),
            /**
             * Whether the task appears because it was created by or assigned to the user.
             *
             * @example assigned
             */
            'state' => $this->when($this->user_id !== auth()->id(), 'assigned', 'created'),
            /**
             * Task creation timestamp in UTC ISO 8601 format.
             *
             * @example 2025-08-10T09:00:00+00:00
             */
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
