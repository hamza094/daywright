<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Project;

use App\Http\Resources\Api\V1\InsightResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class ProjectInsightsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Project insights API response resource
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string,mixed>
     */
    #[Override]
    public function toArray($request): array
    {
        $project = $this['project'];
        $insights = $this['insights'];
        $sections = $this['sections'];

        return [
            /**
             * @var int
             *
             * @example 4
             */
            'project_id' => $project->id,

            /**
             * @example The Universal Dimension
             * */
            'project_name' => $project->name,

            'insights' => InsightResource::collection($insights ?? []),

            /**
             * @example "2025-09-27T20:15:32Z"
             */
            'generated_at' => now()->toISOString(),

            /**
             * @var array<int, string>
             *
             * @example ["health"]
             */
            'sections_requested' => $sections,
        ];
    }
}
