<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Task;

use App\Http\Resources\Api\V1\ApiResourceLink;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use JsonSerializable;
use Override;

/**
 * @mixin \App\Models\Task
 */
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
            /**
             * Task Title
             *
             * @example "The rise of plant"
             */
            'title' => Str::ucfirst($this->title),
            /**
             * Task Description
             *
             * @example "this is the description of task"
             */
            'description' => $this->description,
            /**
             * Task Satus id
             *
             * @example 1
             */

            // 'status_id'=>$this->status_id,

            /**
             * TaskStatus Resource
             */
            'status' => new TaskStatusResource($this->whenLoaded('status')),

            /*
          * Users associated to task
          */
            'members' => $this->whenLoaded(
                'assignee',
                fn () => TaskMemberResource::collection($this->assignee),
            ),

            /**
             * Task notified wheater notificatopn sent to asinee or not
             */
            'notified' => $this->notified,

            /**
             * Task due at in UTC ISO 8601 format.
             *
             * @example 2024-12-09T10:25:00+00:00
             */
            'due_at' => $this->when($this->due_at, fn (): string => $this->due_at->toIso8601String()),

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
                $this->updated_at->isAfter($this->created_at),
                fn (): string => $this->updated_at->toIso8601String(),
            ),
            'links' => [
                'self' => ApiResourceLink::task($this->resource),
            ],
        ];
    }
}
