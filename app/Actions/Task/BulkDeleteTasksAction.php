<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class BulkDeleteTasksAction
{
    private const int CHUNK_SIZE = 200;

    /**
     * Delete tasks and related pivot/activity rows in chunks.
     *
     * @param  array<int,int>  $taskIds
     */
    public function execute(array $taskIds): void
    {
        if ($taskIds === []) {
            return;
        }

        Task::withTrashed()
            ->whereIn('id', $taskIds)
            ->select('id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $tasks): void {
                $chunkTaskIds = $tasks->modelKeys();

                DB::transaction(function () use ($chunkTaskIds): void {
                    $this->deleteTaskAssignees($chunkTaskIds);
                    $this->deleteTaskActivities($chunkTaskIds);
                    $this->forceDeleteTasks($chunkTaskIds);
                });
            }, column: 'id');
    }

    /**
     * @param  array<int, int|string>  $taskIds
     */
    private function deleteTaskAssignees(array $taskIds): void
    {
        DB::table('task_user')
            ->whereIn('task_id', $taskIds)
            ->delete();
    }

    /**
     * @param  array<int, int|string>  $taskIds
     */
    private function deleteTaskActivities(array $taskIds): void
    {
        DB::table('activities')
            ->where('subject_type', Task::class)
            ->whereIn('subject_id', $taskIds)
            ->delete();
    }

    /**
     * @param  array<int, int|string>  $taskIds
     */
    private function forceDeleteTasks(array $taskIds): void
    {
        Task::withTrashed()
            ->whereIn('id', $taskIds)
            ->forceDelete();
    }
}
