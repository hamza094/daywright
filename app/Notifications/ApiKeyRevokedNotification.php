<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApiKeyRevokedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tokenName,
    ) {
        $this->afterCommit = true;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('API Key Revoked — Daywright')
            ->greeting('Security Notice')
            ->line("The API key **\"{$this->tokenName}\"** has been revoked on your Daywright account.")
            ->line('Any integrations using this key will stop working immediately.')
            ->action('Manage API Keys', url('/settings/api-tokens'))
            ->line('If this was unexpected, please review your account security settings.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'api_key_revoked',
            'token_name' => $this->tokenName,
        ];
    }
}
