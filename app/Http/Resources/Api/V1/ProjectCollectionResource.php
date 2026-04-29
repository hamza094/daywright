<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Override;

/**
 * @mixin \App\Models\Project
 */
class ProjectCollectionResource extends JsonResource
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

        if ($request->is('api/v1/dashboard/tasks')) {
            return [
                'name' => $this->name,
                'slug' => $this->slug,
                'links' => [
                    'self' => $this->path(),
                ],
            ];
        }

        return [
            /**
             * @example 1
             */
            'id' => $this->id,

            /**
             * @example The Dimension
             */
            'name' => $this->name,

            /**
             * @example the-dimension
             */
            'slug' => $this->slug,

            /**
             * Project status calculated on the based of score
             *
             * @example cold
             */
            'health_status' => $this->health_status,

            /**
             * Details of the current stage of the project.
             */
            'stage' => new StageResource($this->stage),

            /**
             * The human-readable creation date of the project.
             *
             * @example "2 hours ago"
             */
            'created_at' => $this->created_at->diffForHumans(),

            /**
             * Links related to the project.
             *
             * @example {
             *   "self": "/api/v1/projects/the-dimension"
             * }
             */
            'links' => [
                'self' => $this->path(),
            ],
        ];
    }
}
