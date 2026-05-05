<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks\Zoom;

use App\Enums\MeetingState;
use App\Events\MeetingStatusUpdate;
use App\Models\Meeting;
use App\Notifications\Zoom\MeetingEnded;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class MeetingEndsWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $meeting_id;

    public ?string $start_time;

    public ?string $end_time;

    /**
     * @param  array<string, mixed>  $data
     * @return void
     */
    public function __construct(array $data)
    {
        $this->meeting_id = (int) $data['meeting_id'];
        $this->start_time = $data['start_time'] ?? null;
        $this->end_time = $data['end_time'] ?? null;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $meeting = $this->getMeeting();

            if (! $this->validateStatus($meeting)) {
                return;
            }

            $this->updateMeetingStatus($meeting);
            $this->sendNotifications($meeting);
        } catch (ModelNotFoundException $e) {
            Log::channel('webhook')->error("Meeting with ID {$this->meeting_id} not found in the database.");
            throw $e;
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

    private function getMeeting(): Meeting
    {
        return Meeting::where('meeting_id', $this->meeting_id)->firstOrFail();
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
            'notifier' => $user->getNotifierData(),
        ];

        Notification::send($members, new MeetingEnded($notificationData));
    }

    private function validateStatus(Meeting $meeting): bool
    {
        if ($meeting->status === MeetingState::ENDS->value) {
            Log::channel('webhook')->info("Meeting already ended for meeting_id: {$this->meeting_id}");

            return false;
        }

        return true;
    }
}
