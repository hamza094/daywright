<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Zoom\MeetingDeletedWebhookRequest;
use App\Http\Requests\Api\V1\Zoom\MeetingEndedWebhookRequest;
use App\Http\Requests\Api\V1\Zoom\MeetingStartedWebhookRequest;
use App\Http\Requests\Api\V1\Zoom\MeetingUpdatedWebhookRequest;
use App\Services\Webhooks\ZoomWebhookInboxService;
use Illuminate\Http\JsonResponse;

class ZoomWebhookController extends ApiController
{
    private const string WEBHOOK_ACCEPTED_MESSAGE = 'Webhook accepted.';

    public function update(MeetingUpdatedWebhookRequest $request, ZoomWebhookInboxService $webhookInboxService): JsonResponse
    {
        $webhookInboxService->accept(
            eventKey: $request->attributes->get('zoom_webhook_fingerprint'),
            eventType: 'meeting.updated',
            requestId: $request->header('x-zm-request-id'),
            occurredAt: $request->input('event_ts'),
            data: $request->toDto(),
        );

        return $this->respondWithMessage(self::WEBHOOK_ACCEPTED_MESSAGE);
    }

    public function delete(MeetingDeletedWebhookRequest $request, ZoomWebhookInboxService $webhookInboxService): JsonResponse
    {
        $webhookInboxService->accept(
            eventKey: $request->attributes->get('zoom_webhook_fingerprint'),
            eventType: 'meeting.deleted',
            requestId: $request->header('x-zm-request-id'),
            occurredAt: $request->input('event_ts'),
            data: $request->toDto(),
        );

        return $this->respondWithMessage(self::WEBHOOK_ACCEPTED_MESSAGE);
    }

    public function start(MeetingStartedWebhookRequest $request, ZoomWebhookInboxService $webhookInboxService): JsonResponse
    {
        $webhookInboxService->accept(
            eventKey: $request->attributes->get('zoom_webhook_fingerprint'),
            eventType: 'meeting.started',
            requestId: $request->header('x-zm-request-id'),
            occurredAt: $request->input('event_ts'),
            data: $request->toDto(),
        );

        return $this->respondWithMessage(self::WEBHOOK_ACCEPTED_MESSAGE);
    }

    public function ended(MeetingEndedWebhookRequest $request, ZoomWebhookInboxService $webhookInboxService): JsonResponse
    {
        $webhookInboxService->accept(
            eventKey: $request->attributes->get('zoom_webhook_fingerprint'),
            eventType: 'meeting.ended',
            requestId: $request->header('x-zm-request-id'),
            occurredAt: $request->input('event_ts'),
            data: $request->toDto(),
        );

        return $this->respondWithMessage(self::WEBHOOK_ACCEPTED_MESSAGE);
    }
}
