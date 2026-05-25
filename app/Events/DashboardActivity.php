<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\Api\V1\Admin\ActivitiesResource;
use App\Models\Activity;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Override;

class DashboardActivity implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Activity
     */
    public $activity;

    /**
     * Create a new event instance.
     */
    public function __construct(Activity $activity)
    {
        $this->activity = $activity;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    #[Override]
    public function broadcastOn()
    {
        return new Channel('activities');
    }

    public function broadcastWith()
    {
        return (new ActivitiesResource($this->activity))->resolve();
    }
}
