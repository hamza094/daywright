<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks\Zoom;

use App\Actions\Webhooks\Zoom\HandleMeetingEndedWebhook;
use App\DataTransferObjects\Zoom\MeetingEndedWebhookData;
use Illuminate\Contracts\Queue\ShouldQueue;

class MeetingEndedWebhook extends ZoomMeetingWebhookJob implements ShouldQueue
{
    public MeetingEndedWebhookData $data;

    public function __construct(MeetingEndedWebhookData $data)
    {
        $this->data = $data;
        $this->meeting_id = $data->meetingId;
        $this->request_id = $data->requestId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $handler = app(HandleMeetingEndedWebhook::class);
        $handler->handle($this->data);
    }

    protected function operation(): string
    {
        return 'zoom.webhook.meeting.ended';
    }
}
