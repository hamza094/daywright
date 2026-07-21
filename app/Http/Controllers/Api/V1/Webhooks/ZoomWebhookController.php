<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Zoom\MeetingDeletedWebhookRequest;
use App\Http\Requests\Api\V1\Zoom\MeetingEndedWebhookRequest;
use App\Http\Requests\Api\V1\Zoom\MeetingStartedWebhookRequest;
use App\Http\Requests\Api\V1\Zoom\MeetingUpdatedWebhookRequest;
use App\Services\Webhooks\ZoomWebhookDispatcher;
use Illuminate\Http\JsonResponse;

class ZoomWebhookController extends ApiController
{
    private const string WEBHOOK_ACCEPTED_MESSAGE = 'Webhook accepted.';

    public function __construct(
        private readonly ZoomWebhookDispatcher $dispatcher,
    ) {}

    public function update(MeetingUpdatedWebhookRequest $request): JsonResponse
    {
        $this->dispatcher->dispatchUpdate($request->toDto());

        return $this->respondWithMessage(self::WEBHOOK_ACCEPTED_MESSAGE);
    }

    public function delete(MeetingDeletedWebhookRequest $request): JsonResponse
    {
        $this->dispatcher->dispatchDelete($request->toDto());

        return $this->respondWithMessage(self::WEBHOOK_ACCEPTED_MESSAGE);
    }

    public function start(MeetingStartedWebhookRequest $request): JsonResponse
    {
        $this->dispatcher->dispatchStart($request->toDto());

        return $this->respondWithMessage(self::WEBHOOK_ACCEPTED_MESSAGE);
    }

    public function ended(MeetingEndedWebhookRequest $request): JsonResponse
    {
        $this->dispatcher->dispatchEnded($request->toDto());

        return $this->respondWithMessage(self::WEBHOOK_ACCEPTED_MESSAGE);
    }
}
