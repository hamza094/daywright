<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataTransferObjects\Notification\NotificationActorData;
use App\DataTransferObjects\Notification\NotificationPayloadData;
use App\Notifications\Concerns\QueuesAfterCommit;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskDue extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable, QueuesAfterCommit;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected Carbon $dueDate,
        protected string $taskTitle,
        protected string $notifiedOption,
        protected NotificationActorData $notifierData,
        protected string $projectName,
        protected string $projectSlug
    ) {
        $this->configureQueue();
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(mixed $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line($this->notificationMessage())
            ->action('View Task', $this->taskUrl())
            ->line('Thank you for using our application!');
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
            message: $this->notificationMessage(),
            notifier: $this->notifierData,
            link: $this->projectPath(),
        );
    }

    private function taskUrl(): string
    {
        return NotificationLink::project(projectSlug: $this->projectSlug, absolute: true);
    }

    private function projectPath(): string
    {
        return NotificationLink::project(projectSlug: $this->projectSlug, absolute: false);
    }

    private function notificationMessage(): string
    {
        return "Your task '{$this->taskTitle}' due '{$this->dueDate}' is approaching. You selected to be notified '{$this->notifiedOption}'.";
    }
}
