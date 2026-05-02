<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Jobs\CancelZoomMeetingsJob;
use App\Models\Project;

final readonly class ForceDeleteAbandonedProjectAction
{
    public function __construct(
        private CancelProjectZoomMeetingsAction $cancelProjectZoomMeetingsAction,
    ) {}

    public function execute(Project $project): bool
    {
        if (! $project->trashed()) {
            return false;
        }

        $meetings = $this->cancelProjectZoomMeetingsAction->execute($project);

        if ($meetings !== []) {
            CancelZoomMeetingsJob::dispatch($meetings);
        }

        $project->forceDelete();

        return true;
    }
}
