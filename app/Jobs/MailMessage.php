<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ProjectMail;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class MailMessage implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly int $projectId,
        private readonly int $messageId,
        private readonly int $userId,
        private readonly string $userUuid
    ) {
        $this->onQueue('default');
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['project:'.$this->projectId, 'message:'.$this->messageId, 'user:'.$this->userUuid];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Early return if batch is cancelled
        if ($this->batch()?->cancelled()) {
            return;
        }

        $message = Message::find($this->messageId);
        $project = Project::find($this->projectId);
        $user = User::find($this->userId);

        // Idempotency check: don't send if message is already delivered
        if ($message?->delivered) {
            return;
        }

        if (! $project || ! $message || ! $user) {
            Log::warning('MailMessage job failed: Required models not found', [
                'message_id' => $this->messageId,
                'project_id' => $this->projectId,
                'user_id' => $this->userId,
                'project_exists' => $project !== null,
                'message_exists' => $message !== null,
                'user_exists' => $user !== null,
            ]);

            return;
        }

        Mail::to($user)
            ->send(new ProjectMail($project, $message));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('MailMessage job failed', [
            'message_id' => $this->messageId,
            'project_id' => $this->projectId,
            'user_uuid' => $this->userUuid,
            'exception' => $exception,
        ]);
    }
}
