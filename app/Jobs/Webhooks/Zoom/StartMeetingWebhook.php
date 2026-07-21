<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks\Zoom;

use App\Actions\Webhooks\Zoom\HandleMeetingStartedWebhook;
use App\DataTransferObjects\Zoom\MeetingStartedWebhookData;
use Illuminate\Contracts\Queue\ShouldQueue;

class StartMeetingWebhook extends ZoomMeetingWebhookJob implements ShouldQueue
{
    public function __construct(MeetingStartedWebhookData $data)
    {
        parent::__construct($data);
    }

    /**
     * Execute the job.
     */
    public function handle(HandleMeetingStartedWebhook $handler): void
    {
        $handler->handle($this->data);
    }

    protected function operation(): string
    {
        return 'zoom.webhook.meeting.started';
    }
}
