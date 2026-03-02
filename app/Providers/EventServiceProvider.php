<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\PasswordUpdateEvent;
use App\Events\UserLogin;
use App\Listeners\PaddleErrorListener;
use App\Listeners\PaddleEventListener;
use App\Listeners\SaveUserTimezone;
use App\Listeners\SendPasswordUpdateEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Laravel\Paddle\Events\WebhookHandled;
use Laravel\Paddle\Events\WebhookReceived;
use Override;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        WebhookReceived::class => [
            PaddleEventListener::class,
        ],
        WebhookHandled::class => [
            PaddleErrorListener::class,
        ],
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        UserLogin::class => [
            SaveUserTimezone::class,
        ],
        PasswordUpdateEvent::class => [
            SendPasswordUpdateEmail::class,
        ],
        \App\Events\DashboardActivity::class => [],
        \App\Events\ActivityLogged::class => [],
        \App\Events\NewMessage::class => [],
        \App\Events\MeetingStatusUpdate::class => [],
        \App\Events\ProjectHealthUpdated::class => [],

        /*Verified::class => [
      'App\Listeners\LogVerifiedUser',
    ],*/
    ];

    /**
     * Register any events for your application.
     */
    #[Override]
    public function boot(): void
    {
        parent::boot();
    }
}
