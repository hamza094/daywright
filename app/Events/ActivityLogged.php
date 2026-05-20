<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Activity;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Override;

class ActivityLogged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(public Activity $activity, public int $projectId) {}

    /**
     * Get the channels the event should broadcast on.
     */
    #[Override]
    public function broadcastOn(): Channel
    {
        return new Channel('activities.project.'.$this->projectId);
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return (new ActivityResource($this->activity->loadMissing(['user', 'subject'])))->resolve();
    }

    /**
     * The name of the queue on which to place the  broadcasting job.
     */
    public function broadcastQueue(): string
    {
        return 'activities';
    }
}
