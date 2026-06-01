<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Models\Project;

final class CollectProjectZoomMeetingsForCancellationAction
{
    /**
     * @return array<int, array{meeting_id:int, user_id:int}>
     */
    public function execute(Project $project): array
    {
        return $project
            ->meetings()
            ->whereHas('user')
            ->get(['meeting_id', 'user_id'])
            ->map(fn ($meeting): array => [
                'meeting_id' => (int) $meeting->meeting_id,
                'user_id' => (int) $meeting->user_id,
            ])
            ->values()
            ->all();
    }
}
