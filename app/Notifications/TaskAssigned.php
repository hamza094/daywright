<?php

declare(strict_types=1);

namespace App\Notifications;

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
     *
     * @return void
     */
    public function __construct(
        protected string $taskTitle,
        protected string $projectName,
        protected string $projectPath,
        protected array $notifierData
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
            ->line("{$this->notifierData['name']} has assigned you a new task.")
            ->line("Task: \"{$this->taskTitle}\"")
            ->line("Project: {$this->projectName}")
            ->action('View Project', url($this->projectPath))
            ->line('Thank you for using our application!');
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
        return new BroadcastMessage(
            $this->notificationData()
        );
    }

    /**
     * Prepare the notification data.
     *
     * @return array<string, mixed> The notification data.
     */
    private function notificationData(): array
    {
        return [
            'message' => 'has assigned you a task: "'.$this->taskTitle.'" This is regarding the project '.$this->projectName,
            'notifier' => $this->notifierData,
            'link' => $this->projectPath,
        ];
    }
}
