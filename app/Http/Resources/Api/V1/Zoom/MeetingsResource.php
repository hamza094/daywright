<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Zoom;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Override;

/**
 * @mixin \App\Models\Meeting
 */
class MeetingsResource extends JsonResource
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
        return [
            'id' => $this->id,
            'topic' => Str::headline($this->topic),
            'agenda' => Str::ucfirst($this->agenda),
            'created_at' => $this->created_at?->toIso8601String(),
            'start_time' => $this->when(
                (bool) $this->start_time,
                fn (): string => Carbon::parse($this->start_time)->setTimezone('UTC')->toIso8601String(),
            ),
            'status' => Str::ucfirst($this->status),
            'timezone' => $this->timezone,
        ];
    }
}
