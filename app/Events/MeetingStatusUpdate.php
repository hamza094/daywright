<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\Api\V1\Zoom\MeetingsResource;
use App\Models\Meeting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Override;

class MeetingStatusUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(public Meeting $meeting) {}

    #[Override]
    public function broadcastOn()
    {
        return new PrivateChannel('meetingStatus.'.$this->meeting->id);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return (new MeetingsResource($this->meeting))
            ->resolve();
    }
}
