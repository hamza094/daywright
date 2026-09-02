<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Project;

use App\Http\Resources\Api\V1\User\UserSummaryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\Message
 */
class ScheduledMessageResource extends JsonResource
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
            'id' => $this->id,
            'type' => $this->type,
            'subject' => $this->subject,
            'message' => $this->message,
            /**
             * Message delivery timestamp in UTC ISO 8601 format.
             *
             * @format date-time
             *
             * @example 2025-07-01T09:00:00+00:00
             */
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            /**
             * Message creation timestamp in UTC ISO 8601 format.
             *
             * @format date-time
             *
             * @example 2025-07-01T09:00:00+00:00
             */
            'created_at' => $this->created_at?->toIso8601String(),
            'users' => UserSummaryResource::collection($this->whenLoaded('users')),
        ];
    }
}
