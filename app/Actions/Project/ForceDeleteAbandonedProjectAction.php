<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Jobs\CancelZoomMeetingsJob;
use App\Models\Project;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        return DB::transaction(function () use ($project, $meetings): bool {
            if ($meetings === []) {
                return $this->deleteProjectWithoutMeetings($project);
            }

            return $this->deleteProjectWithMeetings($project, $meetings);
        });
    }

    private function deleteProjectWithoutMeetings(Project $project): bool
    {
        $project->forceDelete();

        return true;
    }

    private function deleteProjectWithMeetings(Project $project, array $meetings): bool
    {
        $jobs = $this->buildCancellationJobs($meetings);
        $this->dispatchCancellationBatch($jobs, $project);
        $project->forceDelete();

        return true;
    }

    private function buildCancellationJobs(array $meetings): array
    {
        return collect($meetings)->map(
            fn ($meeting) => new CancelZoomMeetingsJob($meeting['meeting_id'], $meeting['user_id'])
        )->toArray();
    }

    private function dispatchCancellationBatch(array $jobs, Project $project): void
    {
        Bus::batch($jobs)
            ->allowFailures()
            ->catch(function (Batch $batch, Throwable $e) use ($project): void {
                Log::error('Failed to dispatch Zoom cancellation batch', [
                    'project_id' => $project->id,
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ]);
            })
            ->finally(function (Batch $batch) use ($project): void {
                if ($batch->failedJobs > 0) {
                    Log::warning('Some Zoom meetings failed to cancel', [
                        'project_id' => $project->id,
                        'batch_id' => $batch->id,
                        'failed_jobs' => $batch->failedJobs,
                        'total_jobs' => $batch->totalJobs,
                        'failed_job_ids' => $batch->failedJobIds,
                    ]);
                }
            })
            ->dispatch();
    }
}
