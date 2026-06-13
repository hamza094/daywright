<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks\Zoom;

use App\Actions\Webhooks\Zoom\HandleMeetingDeletedWebhook;
use App\DataTransferObjects\Zoom\MeetingDeletedWebhookData;
use Illuminate\Contracts\Queue\ShouldQueue;

class DeleteMeetingWebhook extends ZoomMeetingWebhookJob implements ShouldQueue
{
    public MeetingDeletedWebhookData $data;

    public function __construct(MeetingDeletedWebhookData $data)
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
        $handler = app(HandleMeetingDeletedWebhook::class);
        $handler->handle($this->data);
    }

    protected function operation(): string
    {
        return 'zoom.webhook.meeting.deleted';
    }
}
