<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Project;

use App\Http\Resources\Api\V1\ApiResourceLink;
use App\Http\Resources\Api\V1\StageResource;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Override;

/**
 * @mixin \App\Models\Project
 */
#[SchemaName('ProjectListItem')]
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
             * Project identifier.
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Project display name.
             *
             * @example The Dimension
             */
            'name' => $this->name,

            /**
             * Stable project slug used in public routes.
             *
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
