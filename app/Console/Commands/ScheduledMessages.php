<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Message;
use App\Services\Project\MessageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScheduledMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:message';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Scheduled project messages to users';

    /**
     * Execute the console command.
     */
    public function handle(MessageService $service): void
    {
        Message::messageScheduled()
            ->with('project')
            ->chunkById(50, function ($messages) use ($service): void {
                foreach ($messages as $message) {
                    /** @var \App\Models\Project|null $project */
                    $project = $message->project;

                    if ($project === null) {
                        continue;
                    }

                    try {
                        $service->sendNow($project, $message);
                    } catch (Throwable $e) {
                        Log::error('ScheduledMessages command: failed to send message', [
                            'message_id' => $message->id,
                            'project_id' => $project->id,
                            'exception' => $e,
                        ]);
                    }
                }
            });
    }
}
