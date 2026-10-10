<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

final class ZoomWebhookTimestamp
{
    private const int MILLISECONDS_THRESHOLD = 100_000_000_000;

    public static function toMilliseconds(?int $timestamp): ?int
    {
        if ($timestamp === null) {
            return null;
        }

        if ($timestamp < 1) {
            return null;
        }

        return $timestamp < self::MILLISECONDS_THRESHOLD
            ? $timestamp * 1000
            : $timestamp;
    }
}
