<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Task;

use App\Http\Resources\Api\V1\ApiResourceLink;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Override;

/**
 * @mixin \App\Models\Task
 */
#[SchemaName('PublicTask')]
class TaskResource extends JsonResource
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
             * Task title.
             *
             * @example The rise of plant
             */
            'title' => Str::ucfirst($this->title),
            /**
             * Task description.
             *
             * @example This is the description of the task.
             */
            'description' => $this->description,
            /**
             * Current task status.
             */
            'status' => new TaskStatusResource($this->whenLoaded('status')),

            /**
             * Users currently assigned to the task.
             */
            'members' => $this->whenLoaded(
                'assignee',
                fn () => TaskMemberResource::collection($this->assignee),
            ),

            /**
             * Reminder strategy for due-date notifications.
             */
            'notified' => $this->notified,

            /**
             * Task due at in UTC ISO 8601 format.
             *
             * @example 2024-12-09T10:25:00+00:00
             */
            'due_at' => $this->when($this->due_at !== null, fn (): string => $this->due_at->toIso8601String()),

            /**
             * Task created at in UTC ISO 8601 format.
             *
             * @example 2024-12-04T11:41:34+00:00
             */
            'created_at' => $this->created_at?->toIso8601String(),

            /**
             * Task updated at in UTC ISO 8601 format if present.
             *
             * @example 2024-12-10T09:41:34+00:00
             */
            'updated_at' => $this->when(
                $this->updated_at !== null && $this->created_at !== null && $this->updated_at->isAfter($this->created_at),
                fn (): string => $this->updated_at->toIso8601String(),
            ),
            /**
             * Route links related to the task.
             */
            'links' => [
                'self' => ApiResourceLink::task($this->resource),
            ],
        ];
    }
}
