<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Message;
use App\Models\Project;
use App\Services\VonageSmsService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SmsMessage implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        private int $projectId,
        private int $messageId
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(VonageSmsService $service): void
    {
        // Early return if batch is cancelled
        if ($this->batch()?->cancelled()) {
            return;
        }

        $message = Message::find($this->messageId);
        $project = Project::find($this->projectId);

        // Idempotency check: don't send if message is already delivered
        if ($message?->delivered) {
            return;
        }

        if (! $project || ! $message) {
            return;
        }

        $service->send($project, $message);
    }
}
