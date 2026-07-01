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
use Illuminate\Support\Facades\Log;

class SmsMessage implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $timeout = 60;
    public bool $failOnTimeout = true;
    public $backoff = [30, 120];

    public function __construct(
        private int $projectId,
        private int $messageId
    ) {
        $this->onQueue('default');
    }

    public function tags(): array
    {
        return ['project:'.$this->projectId, 'message:'.$this->messageId];
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

    public function failed(Throwable $exception): void
    {
        Log::error('SmsMessage job failed', [
            'message_id' => $this->messageId,
            'project_id' => $this->projectId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
