<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks\Zoom;

use App\Actions\Webhooks\Zoom\HandleMeetingStartedWebhook;
use App\DataTransferObjects\Zoom\MeetingStartedWebhookData;
use Illuminate\Contracts\Queue\ShouldQueue;

class StartMeetingWebhook extends ZoomMeetingWebhookJob implements ShouldQueue
{
    public MeetingStartedWebhookData $data;

    public function __construct(MeetingStartedWebhookData $data)
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
        $handler = app(HandleMeetingStartedWebhook::class);
        $handler->handle($this->data);
    }

    protected function operation(): string
    {
        return 'zoom.webhook.meeting.started';
    }
}
