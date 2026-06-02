<?php

declare(strict_types=1);

namespace App\Notifications\Zoom;

use App\Notifications\NotificationLink;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingStarted extends Notification implements ShouldBroadcast
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
        $formattedStartTime = $this->formattedStartTime();

        return (new MailMessage)
            ->subject('Meeting Started: '.$this->data['meeting_topic'])
            ->markdown('mail.meeting.started', [
                'projectName' => $this->data['project_name'],
                'projectLink' => $this->projectUrl(),
                'meetingTopic' => $this->data['meeting_topic'],
                'userName' => $this->data['notifier']['name'],
                'joinUrl' => $this->data['meeting_join_url'],
                'startTime' => $formattedStartTime,
                'timezone' => $this->data['meeting_timezone'],
            ]);
    }

    public function toBroadcast(mixed $notifiable): BroadcastMessage
    {
        $formattedStartTime = $this->formattedStartTime();

        return new BroadcastMessage([
            'message' => 'Project '.$this->data['project_name'].' Meeting '.$this->data['meeting_topic'].' started at '.$formattedStartTime.' '.$this->data['meeting_timezone'],
            'notifier' => $this->data['notifier'],
            'link' => $this->projectPath(),
        ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        $formattedStartTime = $this->formattedStartTime();

        return [
            'message' => 'Project '.$this->data['project_name'].' Meeting '.$this->data['meeting_topic'].' started at '.$formattedStartTime.' '.$this->data['meeting_timezone'],
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

    /**
     * Get the formatted start time for the meeting.
     */
    private function formattedStartTime(): string
    {
        return $this->data['start_time'] ? Carbon::parse($this->data['start_time'])->format('d F \\a\\t H:i:s') : 'an unknown time';
    }
}
