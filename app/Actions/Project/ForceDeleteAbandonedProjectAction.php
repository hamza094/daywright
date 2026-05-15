<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Jobs\CancelZoomMeetingsJob;
use App\Models\Project;

final readonly class ForceDeleteAbandonedProjectAction
{
    public function __construct(
        private CollectProjectZoomMeetingsForCancellationAction $collectProjectZoomMeetingsForCancellationAction,
    ) {}

    public function execute(Project $project): bool
    {
        if (! $project->trashed()) {
            return false;
        }

        $meetings = $this->collectProjectZoomMeetingsForCancellationAction->execute($project);

        if ($meetings !== []) {
            CancelZoomMeetingsJob::dispatch($meetings)->afterCommit();
        }

        $project->forceDelete();

        return true;
    }
}
