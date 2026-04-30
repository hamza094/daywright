<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Project;

use App\Http\Resources\Api\V1\StageResource;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Override;

/**
 * @mixin \App\Models\Project
 */
class ProjectStageResource extends JsonResource
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
             * Project Name
             *
             * @example "The aura of new compass"
             */
            'name' => $this->name,

            /**
             * Project Stage Resource
             */
            'stage' => new StageResource($this->stage),

            /**
             * project postponed reason if any
             *
             * @example "null"
             */
            'postponed_reason' => $this->when($this->postponed_reason !== null, $this->postponed_reason),

            /**
             * The human-readable creation date of the project set in config file.
             *
             * @example "2 hours ago"
             */
            'stage_updated_at' => $this->stage_updated_at->format(config('app.date_formats.exact')),

            /**
             * Links related to the project.
             *
             * @example {
             *   "self": "/api/v1/projects/the-aura-of-new-compass"
             * }
             */
            'links' => [
                'self' => $this->path(),
            ],
        ];
    }
}
