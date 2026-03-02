<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Project;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Override;

class ProjectHealthUpdated implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public string $broadcastQueue = 'metrics';

    public function __construct(public Project $project) {}

    #[Override]
    public function broadcastOn(): PrivateChannel
    {
        // Use a dedicated channel for health updates so listeners can subscribe specifically
        return new PrivateChannel('project.'.$this->project->id.'.health');
    }

    /**
     * Data to broadcast with the event.
     *
     * @return array<string,mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'project_id' => $this->project->id,
            'health_score' => $this->project->health_score,
            'health_status' => $this->project->health_status,
            'calculated_at' => $this->project->health_score_calculated_at?->toISOString(),
        ];
    }
}
