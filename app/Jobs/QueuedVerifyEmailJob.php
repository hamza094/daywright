<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueuedVerifyEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(protected User $user)
    {
        $this->onQueue('critical');
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['user:'.$this->user->uuid, 'verify-email'];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->user->notify(new VerifyEmail);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('QueuedVerifyEmailJob failed', [
            'user_uuid' => $this->user->uuid,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
