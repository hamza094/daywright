<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Meeting;
use App\Notifications\Zoom\MeetingEnded;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class SendMeetingEndedNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public bool $failOnTimeout = true;
    public array $backoff = [10, 60];

    public int $uniqueFor = 300;

    /**
     * @param  array<string, mixed>  $notificationData
     */
    public function __construct(
        public int $meetingId,
        public array $notificationData,
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return "meeting-ended-notification-{$this->meetingId}";
    }

    public function tags(): array
    {
        return ['meeting:'.$this->meetingId];
    }

    public function handle(): void
    {
        $meeting = Meeting::query()
            ->with(['project.asignees', 'project.user'])
            ->findOrFail($this->meetingId);

        if ($meeting->ended_notification_sent_at !== null) {
            return;
        }

        $claimed = Meeting::query()
            ->where('id', $this->meetingId)
            ->whereNull('ended_notification_sent_at')
            ->update(['ended_notification_sent_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        try {
            Notification::send(
                $meeting->project->asignees,
                new MeetingEnded($this->notificationData),
            );
        } catch (Throwable $e) {
            Meeting::query()
                ->where('id', $this->meetingId)
                ->update(['ended_notification_sent_at' => null]);
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Meeting ended notification job failed', [
            'meeting_id' => $this->meetingId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
