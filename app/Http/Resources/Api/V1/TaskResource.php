<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\Task\TaskMemberResource;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use JsonSerializable;
use Override;
use Timezone;

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
        $showRoute = $request->routeIs('tasks.show');

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
            'members' => $this->when(
                $showRoute,
                fn () => TaskMemberResource::collection($this->whenLoaded('assignee'))
            ),

            /**
             * Task Due at UTC timezone
             *
             * @example 2024-12-09T10:25:00.000000
             */
            'due_at_utc' => $this->due_at,

            /**
             * Task notified wheater notificatopn sent to asinee or not
             */
            'notified' => $this->notified,

            /**
             * Task due at at user timezone
             *
             * @example '9th December 2024 3:25:pm'
             */
            'due_at' => $this->when($this->due_at, fn () => Timezone::convertToLocal(Carbon::parse($this->due_at))),

            /**
             * Task created at utc timezone
             *
             * @example 'December 4th 2024, 11:41:34 am'
             */
            'created_at' => $this->formatDate($this->created_at),

            /**
             * Task updated at utc timezone if its present
             *
             * @example 'December 10th 2024, 9:41:34 am'
             */
            'updated_at' => $this->when(
                $this->updated_at->isAfter($this->created_at),
                fn (): string => $this->formatDate($this->updated_at),
            ),
            'links' => [
                'self' => $this->path(),
            ],
        ];
    }

    private function formatDate($date): string
    {
        return $date->isoFormat('MMMM Do YYYY, h:mm:ss a');
    }
}
