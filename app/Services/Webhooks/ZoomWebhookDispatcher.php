<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\DataTransferObjects\Zoom\MeetingDeletedWebhookData;
use App\DataTransferObjects\Zoom\MeetingEndedWebhookData;
use App\DataTransferObjects\Zoom\MeetingStartedWebhookData;
use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use App\Jobs\Webhooks\Zoom\DeleteMeetingWebhook;
use App\Jobs\Webhooks\Zoom\MeetingEndedWebhook;
use App\Jobs\Webhooks\Zoom\StartMeetingWebhook;
use App\Jobs\Webhooks\Zoom\UpdateMeetingWebhook;

final class ZoomWebhookDispatcher
{
    public function dispatchUpdate(MeetingUpdatedWebhookData $data): void
    {
        UpdateMeetingWebhook::dispatch($data);
    }

    public function dispatchDelete(MeetingDeletedWebhookData $data): void
    {
        DeleteMeetingWebhook::dispatch($data);
    }

    public function dispatchStart(MeetingStartedWebhookData $data): void
    {
        StartMeetingWebhook::dispatch($data);
    }

    public function dispatchEnded(MeetingEndedWebhookData $data): void
    {
        MeetingEndedWebhook::dispatch($data);
    }
}
