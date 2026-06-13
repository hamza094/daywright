<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks\Zoom;

use App\Actions\Webhooks\Zoom\HandleMeetingUpdatedWebhook;
use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateMeetingWebhook extends ZoomMeetingWebhookJob implements ShouldQueue
{
    public MeetingUpdatedWebhookData $data;

    public function __construct(MeetingUpdatedWebhookData $data)
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
        $handler = app(HandleMeetingUpdatedWebhook::class);
        $handler->handle($this->data);
    }

    protected function operation(): string
    {
        return 'zoom.webhook.meeting.updated';
    }
}
