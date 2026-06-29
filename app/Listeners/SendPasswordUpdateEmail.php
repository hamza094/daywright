<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PasswordUpdateEvent;
use App\Mail\PasswordUpdate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;


class SendPasswordUpdateEmail implements ShouldQueue
{
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
}
