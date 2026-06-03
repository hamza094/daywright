<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks\Zoom;

use App\Jobs\Webhooks\Zoom\Concerns\InteractsWithZoomWebhookLogging;
use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DeleteMeetingWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, InteractsWithZoomWebhookLogging, Queueable, SerializesModels;

    private const string OPERATION = 'zoom.webhook.meeting.deleted';

    /**
     * @var int|string
     */
    public $meeting_id;

    public ?string $request_id;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->meeting_id = $data['meeting_id'];
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
            $meeting = Meeting::where('meeting_id', $this->meeting_id)->firstOrFail();

            $userUuid = $meeting->user()->value('uuid') ?: null;

            $meeting->delete();

            $this->logWebhookProcessed(self::OPERATION, $userUuid);
        } catch (ModelNotFoundException) {
            $this->logWebhookIgnored(self::OPERATION, 'meeting_missing');

            return;
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
}
