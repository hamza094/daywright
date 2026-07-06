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

    /**
     * @param  array<int, array{meeting_id:int, user_id:int}>  $meetings
     */
    private function deleteProjectWithMeetings(Project $project, array $meetings): bool
    {
        $jobs = $this->buildCancellationJobs($meetings);
        $this->dispatchCancellationBatch($jobs, $project);
        $project->forceDelete();

        return true;
    }

    /**
     * @param  array<int, array{meeting_id:int, user_id:int}>  $meetings
     * @return array<int, CancelZoomMeetingsJob>
     */
    private function buildCancellationJobs(array $meetings): array
    {
        return collect($meetings)->map(
            fn ($meeting): CancelZoomMeetingsJob => new CancelZoomMeetingsJob($meeting['meeting_id'], $meeting['user_id'])
        )->toArray();
    }

    /**
     * @param  array<int, CancelZoomMeetingsJob>  $jobs
     */
    private function dispatchCancellationBatch(array $jobs, Project $project): void
    {
        Bus::batch($jobs)
            ->allowFailures()
            ->catch(function (Batch $batch, Throwable $e) use ($project): void {
                Log::error('Zoom cancellation batch encountered a failing job', [
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
