<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Task;
use App\Models\User;
use App\Notifications\NotificationLink;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Password;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Project::class => \App\Policies\ProjectsPolicy::class,
        User::class => \App\Policies\UsersPolicy::class,
        Task::class => \App\Policies\TasksPolicy::class,
        \App\Models\Conversation::class => \App\Policies\ConversationPolicy::class,
        \App\Models\Meeting::class => \App\Policies\MeetingPolicy::class,

    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Password::defaults(
            fn () => Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
        );

        VerifyEmail::toMailUsing(fn (object $notifiable, string $url) => (new MailMessage)
            ->subject('Verify Email Address')
            ->line('Click the button below to verify your email address.This link will expire after 60 minutes.Please Remember you must be login to get your account verified')
            ->action('Verify Email Address', $url));

        VerifyEmail::$createUrlCallback = fn (User $notifiable): string => NotificationLink::verification(
            user: $notifiable,
            expiration: Carbon::now()->addMinutes(60),
        );

    }
}
