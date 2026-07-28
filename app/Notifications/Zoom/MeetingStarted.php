<?php

declare(strict_types=1);

namespace App\Notifications\Zoom;

use App\DataTransferObjects\Meeting\MeetingNotificationData;
use App\Notifications\NotificationLink;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingStarted extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(protected MeetingNotificationData $data) {}

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
            ->subject('Meeting Started: '.$this->data->meetingTopic)
            ->markdown('mail.meeting.started', [
                'projectName' => $this->data->projectName,
                'projectLink' => $this->projectUrl(),
                'meetingTopic' => $this->data->meetingTopic,
                'userName' => $this->data->notifier['name'],
                'joinUrl' => $this->data->meetingJoinUrl,
                'startTime' => $formattedStartTime,
                'timezone' => $this->data->meetingTimezone,
            ]);
    }

    public function toBroadcast(mixed $notifiable): BroadcastMessage
    {
        $formattedStartTime = $this->formattedStartTime();

        return new BroadcastMessage([
            'message' => 'Project '.$this->data->projectName.' Meeting '.$this->data->meetingTopic.' started at '.$formattedStartTime.' '.$this->data->meetingTimezone,
            'notifier' => $this->data->notifier,
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
            'message' => 'Project '.$this->data->projectName.' Meeting '.$this->data->meetingTopic.' started at '.$formattedStartTime.' '.$this->data->meetingTimezone,
            'notifier' => $this->data->notifier,
            'link' => $this->projectPath(),
        ];
    }

    private function projectPath(): string
    {
        return NotificationLink::project(projectSlug: $this->data->projectSlug, absolute: false);
    }

    private function projectUrl(): string
    {
        return NotificationLink::project(projectSlug: $this->data->projectSlug, absolute: true);
    }

    /**
     * Get the formatted start time for the meeting.
     */
    private function formattedStartTime(): string
    {
        return $this->data->startTime !== '' && $this->data->startTime !== '0'
            ? Carbon::parse($this->data->startTime)->setTimezone($this->meetingTimezone())->format('d F \\a\\t H:i:s')
            : 'an unknown time';
    }

    private function meetingTimezone(): string
    {
        return $this->data->meetingTimezone !== '' ? $this->data->meetingTimezone : 'UTC';
    }
}
