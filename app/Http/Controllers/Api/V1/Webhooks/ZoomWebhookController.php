<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Zoom\WebhookRequest;
use App\Jobs\Webhooks\Zoom\DeleteMeetingWebhook;
use App\Jobs\Webhooks\Zoom\MeetingEndsWebhook;
use App\Jobs\Webhooks\Zoom\StartMeetingWebhook;
use App\Jobs\Webhooks\Zoom\UpdateMeetingWebhook;
use Illuminate\Http\JsonResponse;

class ZoomWebhookController extends ApiController
{
    public function update(WebhookRequest $request): JsonResponse
    {
        $request->validated();

        /** @var array<string, mixed> $object */
        $object = (array) $request->input('payload.object', []);

        UpdateMeetingWebhook::dispatch([
            'meeting_id' => $object['id'],
            'update_data' => collect($object)->except(['id', 'uuid'])->toArray(),
        ]);

        return $this->respondWithMessage('Webhook accepted.');
    }

    public function delete(WebhookRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $object = $validated['payload']['object'];

        DeleteMeetingWebhook::dispatch([
            'meeting_id' => $object['id'],
        ]);

        return $this->respondWithMessage('Webhook accepted.');
    }

    public function start(WebhookRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $object = $validated['payload']['object'];

        StartMeetingWebhook::dispatchAfterResponse([
            'meeting_id' => $object['id'],
            'start_time' => $object['start_time'] ?? null,
        ]);

        return $this->respondWithMessage('Webhook accepted.');
    }

    public function ended(WebhookRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $object = $validated['payload']['object'];

        MeetingEndsWebhook::dispatchAfterResponse([
            'meeting_id' => $object['id'],
            'start_time' => $object['start_time'] ?? null,
            'end_time' => $object['end_time'] ?? null,
        ]);

        return $this->respondWithMessage('Webhook accepted.');
    }
}
