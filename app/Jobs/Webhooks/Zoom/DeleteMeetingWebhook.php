<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks\Zoom;

use App\Actions\Webhooks\Zoom\HandleMeetingDeletedWebhook;
use App\DataTransferObjects\Zoom\MeetingDeletedWebhookData;
use Illuminate\Contracts\Queue\ShouldQueue;

class DeleteMeetingWebhook extends ZoomMeetingWebhookJob implements ShouldQueue
{
    public function __construct(MeetingDeletedWebhookData $data)
    {
        parent::__construct($data);
    }

    /**
     * Execute the job.
     */
    public function handle(HandleMeetingDeletedWebhook $handler): void
    {
        $handler->handle($this->data);
    }

    protected function operation(): string
    {
        return 'zoom.webhook.meeting.deleted';
    }
}
