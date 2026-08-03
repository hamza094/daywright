<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApiKeyCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tokenName,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New API Key Created — Daywright')
            ->greeting('Security Notice')
            ->line("A new API key **\"{$this->tokenName}\"** was just created on your Daywright account.")
            ->line('If you did not create this key, please revoke it immediately from your dashboard.')
            ->action('Manage API Keys', url('/settings/api-tokens'))
            ->line('Thank you for using Daywright.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'api_key_created',
            'token_name' => $this->tokenName,
        ];
    }
}
