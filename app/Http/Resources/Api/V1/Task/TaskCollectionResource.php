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
#[SchemaName('ProjectTaskListItem')]
class TaskCollectionResource extends JsonResource
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
             * @example 1
             */
            'id' => $this->id,

            /**
             * Task title.
             *
             * @example The rise of plant
             */
            'title' => Str::ucfirst($this->title),

            /**
             * Current task status.
             */
            'status' => new TaskStatusResource($this->whenLoaded('status')),

            /**
             * Task due at in UTC ISO 8601 format.
             *
             * @format date-time
             *
             * @example 2024-12-19T15:25:00+00:00
             */
            'due_at' => $this->when($this->due_at !== null, fn (): string => $this->due_at->toIso8601String()),
            /**
             * Task created date time in UTC ISO 8601 format.
             *
             * @format date-time
             *
             * @example 2024-12-15T12:00:00+00:00
             */
            'created_at' => $this->created_at?->toIso8601String(),

            /**
             * Route links related to the task.
             *
             * @example {
             * "self": "/api/v1/projects/the-dimension/tasks/1"
             * }
             */
            'links' => [
                'self' => ApiResourceLink::task($this->resource),
            ],
        ];
    }
}
