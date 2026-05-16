<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataTransferObjects\Notification\NotificationActorData;
use App\DataTransferObjects\Notification\NotificationPayloadData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class UserMentioned extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(
        protected string $projectName,
        protected string $projectSlug,
        protected NotificationActorData $notifierData
    ) {}

    /**
     * Get the notification's delivery channels.
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
        return $this->payload()->toArray();
    }

    /**
     * Get the broadcast representation of the notification.
     *
     * @return BroadcastMessage The broadcast notification data.
     */
    public function toBroadcast(mixed $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload()->toArray());
    }

    private function payload(): NotificationPayloadData
    {
        return new NotificationPayloadData(
            message: 'mentioned you in '.$this->projectName.' '.'group chat',
            notifier: $this->notifierData,
            link: $this->projectLink(),
        );
    }

    private function projectLink(): string
    {
        return NotificationLink::project(projectSlug: $this->projectSlug, absolute: false);
    }
}
