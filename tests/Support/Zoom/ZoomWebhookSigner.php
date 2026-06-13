<?php

declare(strict_types=1);

namespace Tests\Support\Zoom;

use function Safe\json_encode;

class ZoomWebhookSigner
{
    public static function signPayload(array $payload, string $requestId): array
    {
        $timestamp = (string) time();
        $rawPayload = json_encode($payload);

        return [
            'x-zm-request-timestamp' => $timestamp,
            'x-zm-signature' => self::buildSignature($timestamp, $rawPayload),
            'x-zm-request-id' => $requestId,
        ];
    }

    public static function buildSignature(string $timestamp, string $payload): string
    {
        $message = 'v0:'.$timestamp.':'.$payload;

        return 'v0='.hash_hmac('sha256', $message, (string) config('services.zoom.webhook_secret'));
    }

    private static function normalizePayload(array|string $payload): string
    {
        if (is_string($payload)) {
            return $payload;
        }

        return json_encode($payload);
    }
}
