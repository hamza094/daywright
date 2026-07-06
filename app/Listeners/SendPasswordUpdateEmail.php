<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PasswordUpdateEvent;
use App\Mail\PasswordUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPasswordUpdateEmail implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PasswordUpdateEvent $event): void
    {
        Mail::to($event->user)->send(new PasswordUpdate($event->updatedAt));
    }

    public function failed(PasswordUpdateEvent $event, Throwable $exception): void
    {
        Log::error('Failed to send password update email', [
            'user_uuid' => $event->user->uuid,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
