<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks\Zoom;

use App\Actions\Webhooks\Zoom\HandleMeetingUpdatedWebhook;
use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateMeetingWebhook extends ZoomMeetingWebhookJob implements ShouldQueue
{
    public function __construct(MeetingUpdatedWebhookData $data)
    {
        parent::__construct($data);
    }

    /**
     * Execute the job.
     */
    public function handle(HandleMeetingUpdatedWebhook $handler): void
    {
        $handler->handle($this->data);
    }

    protected function operation(): string
    {
        return 'zoom.webhook.meeting.updated';
    }
}
