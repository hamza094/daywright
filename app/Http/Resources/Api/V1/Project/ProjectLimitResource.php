<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Project;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * Computed resource for project usage limits.
 * This resource does not wrap a single Eloquent model.
 *
 * @property array{key: string, label: string, scope: string, limit: array{used: int|null, max: int|null}} $resource
 */
#[SchemaName('ProjectUsageLimit')]
final class ProjectLimitResource extends JsonResource
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
             * Stable limit key used in plan-limit checks.
             *
             * @example tasks_per_project
             */
            'key' => $this['key'],

            /**
             * Human-readable label for the tracked limit.
             *
             * @example Tasks per project
             */
            'label' => $this['label'],

            /**
             * Scope the limit applies to.
             *
             * @example project
             */
            'scope' => $this['scope'],

            /**
             * Current usage count and the maximum allowed value for this limit.
             *
             * @example {"used":4,"max":25}
             */
            'limit' => [
                /**
                 * Current usage value for the limit.
                 *
                 * @example 4
                 */
                'used' => data_get($this->resource, 'limit.used'),
                /**
                 * Maximum allowed value for the limit.
                 *
                 * @example 25
                 */
                'max' => data_get($this->resource, 'limit.max'),
            ],
        ];
    }
}
