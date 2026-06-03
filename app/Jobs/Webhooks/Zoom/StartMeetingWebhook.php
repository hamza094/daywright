<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks\Zoom;

use App\DataTransferObjects\Notification\NotificationActorData;
use App\Enums\MeetingState;
use App\Events\MeetingStatusUpdate;
use App\Jobs\Webhooks\Zoom\Concerns\InteractsWithZoomWebhookLogging;
use App\Models\Meeting;
use App\Notifications\Zoom\MeetingStarted;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Throwable;

class StartMeetingWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, InteractsWithZoomWebhookLogging, Queueable, SerializesModels;

    private const string OPERATION = 'zoom.webhook.meeting.started';

    public int $tries = 3;

    public int $meeting_id;

    public ?string $start_time;

    public ?string $request_id;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->meeting_id = (int) $data['meeting_id'];
        $this->start_time = $data['start_time'] ?? null;
        $this->request_id = isset($data['request_id']) && is_string($data['request_id']) && $data['request_id'] !== ''
            ? $data['request_id']
            : null;
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
        $meeting = null;

        try {
            $meeting = $this->getMeeting();

            if (! $meeting instanceof Meeting) {
                $this->logWebhookIgnored(self::OPERATION, 'meeting_missing');

                return;
            }

            $userUuid = $meeting->user()->value('uuid') ?: null;

            if (! $this->shouldStartMeeting($meeting)) {
                return;
            }

            $this->updateMeetingStatus($meeting);

            $this->sendNotifications($meeting);

            $this->logWebhookProcessed(self::OPERATION, $userUuid);
        } catch (Throwable $exception) {
            $userUuid = null;

            if ($meeting !== null) {
                $userUuid = $meeting->user()->value('uuid') ?: null;
            }

            $this->logWebhookRetryScheduled(self::OPERATION, $exception, $userUuid);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $meeting = Meeting::query()->where('meeting_id', $this->meeting_id)->first();

        $userUuid = null;

        if ($meeting !== null) {
            $userUuid = $meeting->user()->value('uuid') ?: null;
        }

        $this->logWebhookFailed(self::OPERATION, $exception, $userUuid);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 30];
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return $this->zoomWebhookTags(self::OPERATION);
    }

    private function getMeeting(): ?Meeting
    {
        return Meeting::where('meeting_id', $this->meeting_id)->first();
    }

    private function updateMeetingStatus(Meeting $meeting): void
    {
        $meeting->update(['status' => MeetingState::START->value]);

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
            'meeting_join_url' => $meeting->join_url,
            'start_time' => $this->start_time,
            'notifier' => NotificationActorData::fromUser($user)->toArray(),
        ];

        Notification::send($members, new MeetingStarted($notificationData));
    }

    private function shouldStartMeeting(Meeting $meeting): bool
    {

        $userUuid = $meeting->user()->value('uuid') ?: null;

        if ($meeting->status === MeetingState::START->value) {
            $this->logWebhookIgnored(self::OPERATION, 'already_started', $userUuid);

            return false;
        }

        if ($meeting->status === MeetingState::ENDS->value) {
            $this->logWebhookIgnored(self::OPERATION, 'stale_event', $userUuid);

            return false;
        }

        return true;
    }
}
