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
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class MailMessage implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private int $projectId,
        private int $messageId,
        private int $userId
    ) {
        $this->onQueue('default');
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
            return;
        }

        Mail::to($user)
            ->send(new ProjectMail($project, $message));
    }
}
