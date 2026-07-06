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

    public function __construct(protected int $userId)
    {
        $this->onQueue('critical');
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        $user = User::find($this->userId);

        return ['user:'.$user?->uuid, 'verify-email'];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }
        $user->notify(new VerifyEmail);
    }

    public function failed(Throwable $exception): void
    {
        $user = User::find($this->userId);
        Log::error('QueuedVerifyEmailJob failed', [
            'user_uuid' => $user?->uuid,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
