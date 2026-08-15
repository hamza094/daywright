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

    public int $tries = 3;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(protected readonly int $userId, protected readonly string $token)
    {
        $this->onQueue('critical');
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        $user = User::find($this->userId);

        return $user ? ["user:{$user->uuid}", 'password-reset'] : ['password-reset'];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::find($this->userId);

        if ($user === null) {
            Log::warning('QueuedPasswordResetJob: User not found', ['user_id' => $this->userId]);

            return;
        }

        $user->notify(new ResetPassword($this->token));
    }

    public function failed(Throwable $exception): void
    {
        $user = User::find($this->userId);

        Log::error('QueuedPasswordResetJob failed', [
            'user_id' => $this->userId,
            'user_uuid' => $user?->uuid,
            'exception' => $exception,
        ]);
    }
}
