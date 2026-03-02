<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Task;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
        \App\Models\Project::class => \App\Policies\ProjectsPolicy::class,
        \App\Models\User::class => \App\Policies\UsersPolicy::class,
        Task::class => \App\Policies\TasksPolicy::class,
        \App\Models\Conversation::class => \App\Policies\ConversationPolicy::class,

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

        VerifyEmail::$createUrlCallback = fn ($notifiable) => URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'user' => $notifiable->uuid,
                // sha1 is expected by Laravel's verification flow; the URL itself is HMAC-signed via temporarySignedRoute. NOSONAR
                'hash' => sha1((string) $notifiable->getEmailForVerification()),
            ]
        );

    }
}
