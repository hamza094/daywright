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
        $request->validated();

        /** @var array<string, mixed> $object */
        $validated = $request->validated();
        $object = $validated['payload']['object'];

        $this->dispatcher->dispatchUpdate($object, $request->header('x-zm-request-id'));

        return $this->respondWithMessage(self::WEBHOOK_ACCEPTED_MESSAGE);
    }

    public function delete(MeetingDeletedWebhookRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $object = $validated['payload']['object'];

        $this->dispatcher->dispatchDelete($object, $request->header('x-zm-request-id'));

        return $this->respondWithMessage(self::WEBHOOK_ACCEPTED_MESSAGE);
    }

    public function start(MeetingStartedWebhookRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $object = $validated['payload']['object'];

        $this->dispatcher->dispatchStart($object, $request->header('x-zm-request-id'));

        return $this->respondWithMessage(self::WEBHOOK_ACCEPTED_MESSAGE);
    }

    public function ended(MeetingEndedWebhookRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $object = $validated['payload']['object'];

        $this->dispatcher->dispatchEnded($object, $request->header('x-zm-request-id'));

        return $this->respondWithMessage(self::WEBHOOK_ACCEPTED_MESSAGE);
    }
}
