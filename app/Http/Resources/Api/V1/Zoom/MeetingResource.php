<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Zoom;

use App\Http\Resources\Api\V1\UsersResource;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
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
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'topic' => Str::headline($this->topic),
            'agenda' => Str::ucfirst($this->agenda),
            'created_at' => $this->created_at->diffForHumans([
                'parts' => 3,
                'join' => ', ',
            ]),
            'updated_at' => $this->updated_at->diffForHumans(),
            'owner' => new UsersResource($this->whenLoaded('user')),
            'start_time' => Carbon::parse($this->start_time)->format('j F Y, H:i'),
            'duration' => $this->duration,
            'start_url' => $this->start_url,
            'join_url' => $this->join_url,
            'password' => $this->password,
            'status' => Str::ucfirst($this->status),
            'timezone' => $this->timezone,
            'join_before_host' => $this->join_before_host ? 'Yes' : 'No',
        ];
    }
}
