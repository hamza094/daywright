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

    public int $tries = 1;

    public int $uniqueFor = 120;

    /**
     * @param  array<string, mixed>  $notificationData
     */
    public function __construct(
        public int $meetingId,
        public array $notificationData,
    ) {}

    public function uniqueId(): string
    {
        return "meeting-ended-notification-{$this->meetingId}";
    }

    public function handle(): void
    {
        $meeting = Meeting::query()
            ->with(['project.asignees', 'project.user'])
            ->findOrFail($this->meetingId);

        Notification::send(
            $meeting->project->asignees,
            new MeetingEnded($this->notificationData),
        );

        try {
            if ($meeting->ended_notification_sent_at === null) {
                $meeting->update(['ended_notification_sent_at' => now()]);
            }
        } catch (Throwable $e) {
            // Log but don't fail the job - notification was already sent
            Log::warning('Failed to update ended notification flag', [
                'meeting_id' => $this->meetingId,
                'error' => $e->getMessage(),
            ]);
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
