<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataTransferObjects\Notification\NotificationActorData;
use App\DataTransferObjects\Notification\NotificationPayloadData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected string $taskTitle,
        protected string $projectName,
        protected string $projectSlug,
        protected NotificationActorData $notifierData
    ) {
        $this->afterCommit();
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
            ->from('daywright@live.com', 'DayWright')
            ->line("{$this->notifierData->name} has assigned you a new task.")
            ->line("Task: \"{$this->taskTitle}\"")
            ->line("Project: {$this->projectName}")
            ->action('View Project', $this->projectUrl())
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
            message: 'has assigned you a task: "'.$this->taskTitle.'" This is regarding the project '.$this->projectName,
            notifier: $this->notifierData,
            link: $this->projectPath(),
        );
    }

    private function projectPath(): string
    {
        return NotificationLink::project(projectSlug: $this->projectSlug, absolute: false);
    }

    private function projectUrl(): string
    {
        return NotificationLink::project(projectSlug: $this->projectSlug, absolute: true);
    }
}
