<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Task;

use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\TaskStatus
 */
class TaskStatusResource extends JsonResource
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
             * Task status identifier.
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Human-readable status label.
             *
             * @example Not Started
             */
            'label' => $this->label,

            /**
             * Hex color code for UI display.
             *
             * @example #CCCCCC
             */
            'color' => $this->color,
        ];
    }
}
