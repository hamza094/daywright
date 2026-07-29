<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Models\Task;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class BulkDeleteTasksAction
{
    private const int CHUNK_SIZE = 200;

    public function __construct(
        private AuditLogService $auditLogService,
    ) {}

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

        $deletedTaskIds = [];

        Task::withTrashed()
            ->whereIn('id', $taskIds)
            ->select('id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $tasks) use (&$deletedTaskIds): void {
                $chunkTaskIds = $tasks->modelKeys();

                DB::transaction(function () use ($chunkTaskIds, &$deletedTaskIds): void {
                    $this->deleteTaskAssignees($chunkTaskIds);
                    $this->deleteTaskActivities($chunkTaskIds);
                    $this->forceDeleteTasks($chunkTaskIds);
                    $deletedTaskIds = array_merge($deletedTaskIds, $chunkTaskIds);
                });
            }, column: 'id');

        $this->auditLogService->log(
            event: 'destruction.bulk_tasks_deleted',
            auditable: null,
            oldValues: [
                'task_ids' => $deletedTaskIds,
                'count' => count($deletedTaskIds),
            ],
            newValues: null,
            metadata: [
                'bulk_operation' => true,
            ]
        );
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
