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
use Illuminate\Http\Request;

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
        $object = $this->payloadObject($request);

        $this->dispatcher->dispatchUpdate($object, $request->header('x-zm-request-id'));

        return $this->respondWithMessage(self::WEBHOOK_ACCEPTED_MESSAGE);
    }

    public function delete(MeetingDeletedWebhookRequest $request): JsonResponse
    {
        $request->validated();

        /** @var array<string, mixed> $object */
        $object = $this->payloadObject($request);

        $this->dispatcher->dispatchDelete($object, $request->header('x-zm-request-id'));

        return $this->respondWithMessage(self::WEBHOOK_ACCEPTED_MESSAGE);
    }

    public function start(MeetingStartedWebhookRequest $request): JsonResponse
    {
        $request->validated();

        /** @var array<string, mixed> $object */
        $object = $this->payloadObject($request);

        $this->dispatcher->dispatchStart($object, $request->header('x-zm-request-id'));

        return $this->respondWithMessage(self::WEBHOOK_ACCEPTED_MESSAGE);
    }

    public function ended(MeetingEndedWebhookRequest $request): JsonResponse
    {
        $request->validated();

        /** @var array<string, mixed> $object */
        $object = $this->payloadObject($request);

        $this->dispatcher->dispatchEnded($object, $request->header('x-zm-request-id'));

        return $this->respondWithMessage(self::WEBHOOK_ACCEPTED_MESSAGE);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadObject(Request $request): array
    {
        $object = $request->input('payload.object');

        return is_array($object) ? $object : [];
    }
}
