<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks\Zoom;

use App\Actions\Webhooks\Zoom\HandleMeetingEndedWebhook;
use App\DataTransferObjects\Zoom\MeetingEndedWebhookData;
use Illuminate\Contracts\Queue\ShouldQueue;

class MeetingEndedWebhook extends ZoomMeetingWebhookJob implements ShouldQueue
{
    public function __construct(public MeetingEndedWebhookData $data)
    {
        parent::__construct(
            meetingId: (int) $this->data->meetingId,
            requestId: $this->data->requestId,
        );
    }

    /**
     * Execute the job.
     */
    public function handle(HandleMeetingEndedWebhook $handler): void
    {
        $handler->handle($this->data);
    }

    protected function operation(): string
    {
        return 'zoom.webhook.meeting.ended';
    }
}
