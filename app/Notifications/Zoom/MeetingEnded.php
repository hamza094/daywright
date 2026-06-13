<?php

declare(strict_types=1);

namespace App\Notifications\Zoom;

use App\Notifications\NotificationLink;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingEnded extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  array<string, mixed>  $data
     */
    public function __construct(protected array $data) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
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
            ->subject('Meeting Ended: '.$this->data['meeting_topic'])
            ->markdown('mail.meeting.ended', [
                'projectName' => $this->data['project_name'],
                'projectLink' => $this->projectUrl(),
                'meetingTopic' => $this->data['meeting_topic'],
                'userName' => $this->data['notifier']['name'],
                'startTime' => $this->formattedStartTime(),
                'endTime' => $this->formattedEndTime(),
                'timezone' => $this->data['meeting_timezone'],
            ]);
    }

    public function toBroadcast(mixed $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'message' => 'Project '.$this->data['project_name'].' Meeting '.$this->data['meeting_topic'].' ended at '.$this->formattedEndTime().' '.$this->data['meeting_timezone'],
            'notifier' => $this->data['notifier'],
            'link' => $this->projectPath(),
        ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'message' => 'Project '.$this->data['project_name'].' Meeting '.$this->data['meeting_topic'].' ended at '.$this->formattedEndTime().' '.$this->data['meeting_timezone'],
            'notifier' => $this->data['notifier'],
            'link' => $this->projectPath(),
        ];
    }

    private function projectPath(): string
    {
        return NotificationLink::project(projectSlug: $this->data['project_slug'], absolute: false);
    }

    private function projectUrl(): string
    {
        return NotificationLink::project(projectSlug: $this->data['project_slug'], absolute: true);
    }

    private function formattedStartTime(): string
    {
        return $this->data['start_time'] ? Carbon::parse($this->data['start_time'])->format('d F \\a\\t H:i:s') : '';
    }

    private function formattedEndTime(): string
    {
        return $this->data['end_time'] ? Carbon::parse($this->data['end_time'])->format('d F \\a\\t H:i:s') : '';
    }
}
