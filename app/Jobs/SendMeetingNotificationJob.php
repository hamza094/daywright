<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DataTransferObjects\Meeting\MeetingNotificationData;
use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification as NotificationClass;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

abstract class SendMeetingNotificationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $meetingId,
        public readonly MeetingNotificationData $notificationData,
    ) {
        $this->onQueue('default');
    }

    /**
     * Get the unique ID prefix for this notification type.
     */
    abstract protected function uniqueIdPrefix(): string;

    /**
     * Create the notification instance.
     */
    abstract protected function createNotification(MeetingNotificationData $data): NotificationClass;

    /**
     * Check if the notification has already been sent.
     */
    abstract protected function hasAlreadySentNotification(Meeting $meeting): bool;

    /**
     * Mark the notification as sent in the database.
     */
    abstract protected function markNotificationAsSent(Meeting $meeting): void;

    /**
     * Get the log message for failed jobs.
     */
    abstract protected function failedLogMessage(): string;

    public function uniqueId(): string
    {
        return $this->uniqueIdPrefix()."-{$this->meetingId}";
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['meeting:'.$this->meetingId];
    }

    public function handle(): void
    {
        $meeting = Meeting::query()
            ->with(['project.asignees', 'project.user'])
            ->findOrFail($this->meetingId);

        if ($this->hasAlreadySentNotification($meeting)) {
            return;
        }

        Notification::send(
            $meeting->project->asignees,
            $this->createNotification($this->notificationData),
        );

        $this->markNotificationAsSent($meeting);
    }

    public function failed(Throwable $exception): void
    {
        Log::error($this->failedLogMessage(), [
            'meeting_id' => $this->meetingId,
            'exception' => $exception,
        ]);
    }
}
