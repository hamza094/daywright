<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Task;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use JsonSerializable;
use Override;

/**
 * @mixin \App\Models\Task
 */
class TaskCollectionResource extends JsonResource
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
            /**
             * Task Id
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Task Title
             *
             * @example "The rise of plant"
             */
            'title' => Str::ucfirst($this->title),

            /**
             * TaskStatus Resource
             */
            'status' => new TaskStatusResource($this->whenLoaded('status')),

            /**
             * Task due at in UTC ISO 8601 format.
             *
             * @example 2024-12-19T15:25:00+00:00
             */
            'due_at' => $this->when($this->due_at, fn (): string => $this->due_at->toIso8601String()),
            /**
             * Task created date time in UTC ISO 8601 format.
             *
             * @example 2024-12-15T12:00:00+00:00
             */
            'created_at' => $this->created_at?->toIso8601String(),

            /**
             * Links related to the project.
             *
             * @example {
             * "self": "/api/v1/projects/the-dimension/tasks/1"
             * }
             */
            'links' => [
                'self' => $this->path(),
            ],
        ];
    }
}
