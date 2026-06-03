<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks\Zoom;

use App\DataTransferObjects\Zoom\MeetingWebhookUpdateData;
use App\Jobs\Webhooks\Zoom\Concerns\InteractsWithZoomWebhookLogging;
use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class UpdateMeetingWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, InteractsWithZoomWebhookLogging, Queueable, SerializesModels;

    private const string OPERATION = 'zoom.webhook.meeting.updated';

    public int $tries = 3;

    public int $meeting_id;

    public ?string $request_id;

    /**
     * @var array<string, mixed>
     */
    public $update_data;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->meeting_id = (int) $data['meeting_id'];
        $this->update_data = MeetingWebhookUpdateData::normalizeChanges((array) ($data['update_data'] ?? []));
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
            $meeting = Meeting::where('meeting_id', $this->meeting_id)->first();

            if (! $meeting) {
                $this->logWebhookIgnored(self::OPERATION, 'meeting_missing');

                return;
            }

            $userUuid = $meeting->user()->value('uuid') ?: null;

            if (! $this->isMeetingUpdated($meeting, $this->update_data)) {
                $this->logWebhookIgnored(self::OPERATION, 'no_changes', $userUuid);

                return;
            }

            $meeting->update($this->update_data);

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

    /**
     * @param  array<string, mixed>  $updateData
     */
    private function isMeetingUpdated(Meeting $meeting, array $updateData): bool
    {
        foreach ($updateData as $key => $value) {
            if ($value !== $meeting->$key) {
                return true;
            }
        }

        return false;
    }
}
