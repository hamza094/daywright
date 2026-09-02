<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Zoom;

use App\Http\Resources\Api\V1\ApiResourceLink;
use App\Http\Resources\Api\V1\User\UserSummaryResource;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\Meeting
 */
class MeetingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray($request): array
    {
        $canAccessProject = $this->relationLoaded('project')
            && $request->user()?->can('access', $this->project);

        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'topic' => $this->topic,
            'agenda' => $this->agenda,
            /**
             * Meeting creation timestamp in UTC ISO 8601 format.
             *
             * @format date-time
             *
             * @example 2025-07-01T09:00:00+00:00
             */
            'created_at' => $this->created_at?->toIso8601String(),
            /**
             * Meeting update timestamp in UTC ISO 8601 format.
             *
             * @format date-time
             *
             * @example 2025-07-01T09:00:00+00:00
             */
            'updated_at' => $this->updated_at?->toIso8601String(),
            'owner' => $this->whenLoaded('user', fn (): UserSummaryResource => new UserSummaryResource($this->user)),
            /**
             * Meeting start time in UTC ISO 8601 format.
             *
             * @format date-time
             *
             * @example 2025-07-01T09:00:00+00:00
             */
            'start_time' => $this->when(
                (bool) $this->start_time,
                fn (): string => Carbon::parse($this->start_time)->setTimezone('UTC')->toIso8601String(),
            ),
            'duration' => $this->duration,
            /**
             * Meeting start URL for the host.
             *
             * @format uri
             */
            'start_url' => $this->when(
                $request->user()?->is($this->user),
                $this->start_url,
            ),
            /**
             * Meeting join URL for participants.
             *
             * @format uri
             */
            'join_url' => $this->when(
                $canAccessProject,
                $this->join_url,
            ),
            'password' => $this->when(
                $canAccessProject,
                $this->password,
            ),
            'status' => $this->status,
            'timezone' => $this->timezone,
            'join_before_host' => (bool) $this->join_before_host,
            'sync_status' => $this->sync_status->value,
            'links' => $this->whenLoaded('project', fn (): array => [
                'self' => ApiResourceLink::meeting($this->resource),
            ]),
        ];
    }
}
