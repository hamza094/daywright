<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Meeting;
use App\Notifications\Zoom\MeetingStarted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class SendMeetingStartedNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    /**
     * @param  array<string, mixed>  $notificationData
     */
    public function __construct(
        public int $meetingId,
        public array $notificationData,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function uniqueId(): string
    {
        return "meeting-started-notification-{$this->meetingId}";
    }

    public function handle(): void
    {
        $meeting = Meeting::query()
            ->with(['project.asignees', 'project.user'])
            ->findOrFail($this->meetingId);

        // Double-check flag before sending (defense-in-depth)
        if ($meeting->started_notification_sent_at !== null) {
            return;
        }

        Notification::send(
            $meeting->project->asignees,
            new MeetingStarted($this->notificationData),
        );

        $meeting->whereNull('started_notification_sent_at')
            ->update(['started_notification_sent_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Meeting started notification job failed', [
            'meeting_id' => $this->meetingId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
