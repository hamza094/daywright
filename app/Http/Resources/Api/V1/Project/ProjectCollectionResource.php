<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Project;

use App\Http\Resources\Api\V1\ApiResourceLink;
use App\Http\Resources\Api\V1\StageResource;
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
             * Project creation timestamp in UTC ISO 8601 format.
             *
             * @example "2024-06-04T00:00:00+00:00"
             */
            'created_at' => $this->created_at?->toIso8601String(),

            /**
             * Links related to the project.
             *
             * @example {
             *   "self": "/api/v1/projects/the-dimension"
             * }
             */
            'links' => [
                'self' => ApiResourceLink::project($this->resource),
            ],
        ];
    }
}
