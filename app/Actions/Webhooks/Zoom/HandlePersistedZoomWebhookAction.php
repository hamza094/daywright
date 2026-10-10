<?php

declare(strict_types=1);

namespace App\Actions\Webhooks\Zoom;

use App\DataTransferObjects\Zoom\MeetingCreatedWebhookData;
use App\DataTransferObjects\Zoom\MeetingDeletedWebhookData;
use App\DataTransferObjects\Zoom\MeetingEndedWebhookData;
use App\DataTransferObjects\Zoom\MeetingStartedWebhookData;
use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use App\Models\WebhookInbox;
use InvalidArgumentException;

final readonly class HandlePersistedZoomWebhookAction
{
    public function __construct(
        private HandleMeetingCreatedWebhook $handleMeetingCreated,
        private HandleMeetingUpdatedWebhook $handleMeetingUpdated,
        private HandleMeetingStartedWebhook $handleMeetingStarted,
        private HandleMeetingEndedWebhook $handleMeetingEnded,
        private HandleMeetingDeletedWebhook $handleMeetingDeleted,
    ) {}

    public function execute(WebhookInbox $webhookInbox): void
    {
        match ($webhookInbox->event_type) {
            'meeting.created' => $this->handleMeetingCreated->handle(
                MeetingCreatedWebhookData::fromArray($webhookInbox->payload),
            ),
            'meeting.updated' => $this->handleMeetingUpdated->handle(
                MeetingUpdatedWebhookData::fromArray($webhookInbox->payload),
                $webhookInbox->provider_occurred_at,
            ),
            'meeting.started' => $this->handleMeetingStarted->handle(
                MeetingStartedWebhookData::fromArray($webhookInbox->payload),
            ),
            'meeting.ended' => $this->handleMeetingEnded->handle(
                MeetingEndedWebhookData::fromArray($webhookInbox->payload),
            ),
            'meeting.deleted' => $this->handleMeetingDeleted->handle(
                MeetingDeletedWebhookData::fromArray($webhookInbox->payload),
                $webhookInbox->provider_occurred_at,
            ),
            default => throw new InvalidArgumentException(
                "Unsupported Zoom webhook event type: {$webhookInbox->event_type}",
            ),
        };
    }
}
