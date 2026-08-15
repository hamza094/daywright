<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks\Zoom;

use App\Jobs\Webhooks\Zoom\Concerns\InteractsWithZoomWebhookLogging;
use App\Models\Meeting;
use App\Services\Webhooks\ZoomWebhookLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

abstract class ZoomMeetingWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, InteractsWithZoomWebhookLogging, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly object $data,
    ) {
        $this->onQueue('webhooks');
    }

    abstract protected function operation(): string;

    public function getMeetingId(): int
    {
        return (int) $this->data->meetingId;
    }

    public function getRequestId(): ?string
    {
        return $this->data->requestId ?? null;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(key: "zoom-meeting:{$this->getMeetingId()}", releaseAfter: 5))
                ->shared()
                ->expireAfter(120),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [15, 30, 60, 120, 240];
    }

    public function failed(Throwable $exception): void
    {
        $meeting = Meeting::query()->where('meeting_id', $this->getMeetingId())->first();

        $userUuid = null;

        if ($meeting !== null) {
            $userUuid = $meeting->user()->value('uuid') ?: null;
        }

        app(ZoomWebhookLogger::class)->logWebhookFailed(
            operation: $this->operation(),
            meetingId: $this->getMeetingId(),
            requestId: $this->getRequestId(),
            exception: $exception,
            userIdentifier: $userUuid,
        );
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return $this->zoomWebhookTags($this->operation());
    }
}
