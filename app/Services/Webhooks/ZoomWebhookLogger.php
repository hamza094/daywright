<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Services\Zoom\ZoomLogContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class ZoomWebhookLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function logWebhookProcessed(
        string $operation,
        int|string $meetingId,
        ?string $requestId,
        int|string|null $userIdentifier = null,
        array $context = []
    ): void {
        $this->logWebhook('info', 'zoom_webhook_processed', $operation, $meetingId, $requestId, $userIdentifier, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logWebhookIgnored(
        string $operation,
        int|string $meetingId,
        ?string $requestId,
        string $reason,
        int|string|null $userIdentifier = null,
        array $context = []
    ): void {
        $this->logWebhook('info', 'zoom_webhook_ignored', $operation, $meetingId, $requestId, $userIdentifier, [
            'reason' => $reason,
            ...$context,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logWebhookRetryScheduled(
        string $operation,
        int|string $meetingId,
        ?string $requestId,
        Throwable $exception,
        int|string|null $userIdentifier = null,
        array $context = []
    ): void {
        $this->logWebhook('warning', 'zoom_webhook_retry_scheduled', $operation, $meetingId, $requestId, $userIdentifier, [
            'exception' => $exception::class,
            'message' => Str::limit($this->sanitize($exception->getMessage()), 1000),
            ...$context,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logWebhookFailed(
        string $operation,
        int|string $meetingId,
        ?string $requestId,
        Throwable $exception,
        int|string|null $userIdentifier = null,
        array $context = []
    ): void {
        $this->logWebhook('error', 'zoom_webhook_failed', $operation, $meetingId, $requestId, $userIdentifier, [
            'exception' => $exception::class,
            'message' => Str::limit($this->sanitize($exception->getMessage()), 1000),
            ...$context,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logWebhook(
        string $level,
        string $event,
        string $operation,
        int|string $meetingId,
        ?string $requestId,
        int|string|null $userIdentifier,
        array $context
    ): void {
        Log::channel('zoom')->{$level}(
            $event,
            ZoomLogContext::forWebhook(
                operation: $operation,
                meetingId: $meetingId,
                requestId: $requestId,
                userId: $userIdentifier,
                context: $context,
            ),
        );
    }

    private function sanitize(string $message): string
    {
        $patterns = [
            '/access_token["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
            '/refresh_token["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
            '/authorization["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
            '/webhook_secret["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
            '/start_url["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
            '/token["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
            '/secret["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
            '/password["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
            '/api[_-]?key["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
        ];

        foreach ($patterns as $pattern) {
            $message = preg_replace($pattern, '[REDACTED]', $message);
        }

        return $message;
    }
}
