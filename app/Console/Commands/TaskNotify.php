<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\SendTaskDueNotificationAction;
use App\Models\Task;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TaskNotify extends Command
{
    protected $signature = 'tasks:notify';

    protected $description = 'Send tasks due notification on scheduled time';

    public function __construct(protected SendTaskDueNotificationAction $sendTaskDueNotificationAction)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Task::dueForNotifications()
            ->whereHas('project', function ($query): void {
                $query->whereNull('deleted_at');
            })
            ->with([
                'assignee:id,name',
                'project:id,name,slug',
            ])
            ->chunk(50, fn ($tasks) => $this->processTasks($tasks));

        $this->info('Task notifications sent successfully.');

        return 0;
    }

    /**
     * Process each task in the chunk.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Task>  $tasks
     */
    private function processTasks($tasks): void
    {
        foreach ($tasks as $task) {
            try {
                /** @var Task $task */
                $this->sendTaskDueNotificationAction->execute($task);
            } catch (Exception $e) {
                Log::error('Failed to process task notification', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
