<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * Insight item resource
 */
class InsightResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string,mixed>
     */
    #[Override]
    public function toArray($request): array
    {
        // The underlying builders return arrays; handle both array and object resources
        $item = $this->resource;

        $type = isset($item['type']) ? (string) $item['type'] : ($this->type ?? 'info');
        $title = isset($item['title']) ? (string) $item['title'] : ($this->title ?? '');
        $message = isset($item['message']) ? (string) $item['message'] : ($this->message ?? '');
        $data = $item['data'] ?? ($this->data ?? []);

        return [
            /**
             * @example "info"
             */
            'type' => $type,

            /**
             * @example "Good Project Health"
             */
            'title' => $title,

            /**
             * @example "Good project health at 71.0%..."
             */
            'message' => $message,

            /**
             * @example {"value":71.0}
             */
            'data' => is_array($data) ? $data : [],
        ];
    }
}
