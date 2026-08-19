<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Project;

use App\Http\Resources\Api\V1\ApiResourceLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\Project
 */
class ProjectSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            /**
             * Project identifier.
             *
             * @example 1
             */
            'id' => $this->id,
            /**
             * Stable project slug used in public routes.
             *
             * @example the-dimension
             */
            'slug' => $this->slug,
            /**
             * Project display name.
             *
             * @example The Dimension
             */
            'name' => $this->name,
            /**
             * API resource links for navigation.
             *
             * @example {"self":"/api/v1/projects/the-dimension"}
             */
            'links' => [
                'self' => ApiResourceLink::project($this->resource),
            ],
        ];
    }
}
