<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ProjectInvitation extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected string $projectName,
        protected string $projectSlug,
        protected array $notifierData
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<string> The channels through which the notification is delivered.
     */
    public function via(mixed $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed> The notification data.
     */
    public function toArray(mixed $notifiable): array
    {
        return $this->notificationData();
    }

    /**
     * Get the broadcast representation of the notification.
     *
     * @return BroadcastMessage The broadcast notification data.
     */
    public function toBroadcast(mixed $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->notificationData());
    }

    /**
     * Prepare the notification data.
     *
     * @return array<string, mixed> The notification data.
     */
    private function notificationData(): array
    {
        return [
            'message' => 'Sent you a project '.$this->projectName.' invitation',
            'notifier' => $this->notifierData,
            'link' => $this->projectLink(),
        ];
    }

    private function projectLink(): string
    {
        return NotificationLink::project(projectSlug: $this->projectSlug, absolute: false);
    }
}
