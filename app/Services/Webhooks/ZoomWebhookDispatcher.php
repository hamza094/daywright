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
    /**
     * @param  array<string, mixed>  $object
     */
    public function dispatchUpdate(array $object, ?string $requestId): void
    {
        $data = MeetingUpdatedWebhookData::fromPayloadObject($object, $requestId);

        UpdateMeetingWebhook::dispatch($data);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    public function dispatchDelete(array $object, ?string $requestId): void
    {
        $data = MeetingDeletedWebhookData::fromPayloadObject($object, $requestId);

        DeleteMeetingWebhook::dispatch($data);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    public function dispatchStart(array $object, ?string $requestId): void
    {
        $data = MeetingStartedWebhookData::fromPayloadObject($object, $requestId);

        StartMeetingWebhook::dispatch($data);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    public function dispatchEnded(array $object, ?string $requestId): void
    {
        $data = MeetingEndedWebhookData::fromPayloadObject($object, $requestId);

        MeetingEndedWebhook::dispatch($data);
    }
}
