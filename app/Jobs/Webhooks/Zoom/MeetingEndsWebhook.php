<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks\Zoom;

use App\DataTransferObjects\Notification\NotificationActorData;
use App\Enums\MeetingState;
use App\Events\MeetingStatusUpdate;
use App\Models\Meeting;
use App\Notifications\Zoom\MeetingEnded;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class MeetingEndsWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $meeting_id;

    public ?string $start_time;

    public ?string $end_time;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->meeting_id = (int) $data['meeting_id'];
        $this->start_time = $data['start_time'] ?? null;
        $this->end_time = $data['end_time'] ?? null;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(key: "zoom-meeting:{$this->meeting_id}", releaseAfter: 5))
                ->shared()
                ->expireAfter(120),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $meeting = $this->getMeeting();

            if (! $meeting instanceof Meeting) {
                Log::channel('webhook')->info('Meeting not found for ended webhook', ['meeting_id' => $this->meeting_id]);

                return;
            }

            if (! $this->shouldEndMeeting($meeting)) {
                return;
            }

            $this->updateMeetingStatus($meeting);
            $this->sendNotifications($meeting);
        } catch (Exception $e) {
            Log::channel('webhook')->error('Error processing meeting ending webhook: '.$e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Meeting Ended webhook job failed', [
            'meeting_id' => $this->meeting_id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 30];
    }

    private function getMeeting(): ?Meeting
    {
        return Meeting::where('meeting_id', $this->meeting_id)->first();
    }

    private function updateMeetingStatus(Meeting $meeting): void
    {
        $meeting->update(['status' => MeetingState::ENDS->value]);
        event(new MeetingStatusUpdate($meeting));
    }

    private function sendNotifications(Meeting $meeting): void
    {
        $project = $meeting->project()->with(['asignees', 'user'])->firstOrFail();
        $user = $project->user;
        $members = $project->asignees;

        $notificationData = [
            'project_name' => $project->name,
            'project_slug' => $project->slug,
            'meeting_topic' => $meeting->topic,
            'meeting_timezone' => $meeting->timezone,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'notifier' => NotificationActorData::fromUser($user)->toArray(),
        ];

        Notification::send($members, new MeetingEnded($notificationData));
    }

    private function shouldEndMeeting(Meeting $meeting): bool
    {
        if ($meeting->status === MeetingState::ENDS->value) {
            Log::channel('webhook')->info("Meeting already ended for meeting_id: {$this->meeting_id}");

            return false;
        }

        return true;
    }
}
