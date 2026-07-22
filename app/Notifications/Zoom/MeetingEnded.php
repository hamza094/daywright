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

class MeetingEnded extends Notification implements ShouldBroadcast, ShouldQueue
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
        return (new MailMessage)
            ->subject('Meeting Ended: '.$this->data->meetingTopic)
            ->markdown('mail.meeting.ended', [
                'projectName' => $this->data->projectName,
                'projectLink' => $this->projectUrl(),
                'meetingTopic' => $this->data->meetingTopic,
                'userName' => $this->data->notifier['name'],
                'startTime' => $this->formattedStartTime(),
                'endTime' => $this->formattedEndTime(),
                'timezone' => $this->data->meetingTimezone,
            ]);
    }

    public function toBroadcast(mixed $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'message' => 'Project '.$this->data->projectName.' Meeting '.$this->data->meetingTopic.' ended at '.$this->formattedEndTime().' '.$this->data->meetingTimezone,
            'notifier' => $this->data->notifier,
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
            'message' => 'Project '.$this->data->projectName.' Meeting '.$this->data->meetingTopic.' ended at '.$this->formattedEndTime().' '.$this->data->meetingTimezone,
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

    private function formattedStartTime(): string
    {
        return $this->data->startTime
            ? Carbon::parse($this->data->startTime)->setTimezone($this->meetingTimezone())->format('d F \\a\\t H:i:s')
            : '';
    }

    private function formattedEndTime(): string
    {
        return $this->data->endTime
            ? Carbon::parse($this->data->endTime)->setTimezone($this->meetingTimezone())->format('d F \\a\\t H:i:s')
            : '';
    }

    private function meetingTimezone(): string
    {
        return $this->data->meetingTimezone !== '' ? $this->data->meetingTimezone : 'UTC';
    }
}
