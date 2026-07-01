<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueuedPasswordResetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;
    public bool $failOnTimeout = true;

    public function __construct(protected User $user, protected string $token)
    {
        $this->onQueue('critical');
    }

    public function tags(): array
    {
        return ['user:'.$this->user->uuid, 'password-reset'];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // This queued job sends
        // Illuminate\Auth\Notifications\ResetPassword notification
        // to the user by triggering the notification
        $this->user->notify(new ResetPassword($this->token));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('QueuedPasswordResetJob failed', [
            'user_uuid' => $this->user->uuid,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
