<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks\Zoom\Concerns;

use App\Services\Zoom\ZoomLogContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @property int|string $meeting_id
 * @property ?string $request_id
 * @property int $tries
 */
trait InteractsWithZoomWebhookLogging
{
    public int $timeout = 120;

    public bool $failOnTimeout = true;

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logWebhookProcessed(string $operation, int|string|null $userIdentifier = null, array $context = []): void
    {
        $this->logWebhook('info', 'zoom_webhook_processed', $operation, $userIdentifier, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logWebhookIgnored(string $operation, string $reason, int|string|null $userIdentifier = null, array $context = []): void
    {
        $this->logWebhook('info', 'zoom_webhook_ignored', $operation, $userIdentifier, [
            'reason' => $reason,
            ...$context,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logWebhookRetryScheduled(string $operation, Throwable $exception, int|string|null $userIdentifier = null, array $context = []): void
    {
        $this->logWebhook('warning', 'zoom_webhook_retry_scheduled', $operation, $userIdentifier, [
            'attempt' => $this->currentAttempt(),
            'max_tries' => $this->tries,
            'retry_after_seconds' => $this->retryDelayForCurrentAttempt(),
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            ...$context,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logWebhookFailed(string $operation, Throwable $exception, int|string|null $userIdentifier = null, array $context = []): void
    {
        $this->logWebhook('error', 'zoom_webhook_failed', $operation, $userIdentifier, [
            'max_tries' => $this->tries,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            ...$context,
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function zoomWebhookTags(string $operation): array
    {
        $tags = [
            'provider:zoom',
            'zoom_operation:'.$operation,
            'meeting:'.$this->meeting_id,
        ];

        if (is_string($this->request_id) && $this->request_id !== '') {
            $tags[] = 'request:'.$this->request_id;
        }

        return $tags;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logWebhook(string $level, string $event, string $operation, int|string|null $userIdentifier, array $context): void
    {
        Log::channel('zoom')->{$level}(
            $event,
            ZoomLogContext::forWebhook(
                operation: $operation,
                meetingId: $this->meeting_id,
                requestId: $this->request_id,
                userId: $userIdentifier,
                context: $context,
            ),
        );
    }

    private function currentAttempt(): int
    {
        return property_exists($this, 'job') && $this->job !== null
            ? $this->attempts()
            : 1;
    }

    private function retryDelayForCurrentAttempt(): ?int
    {
        $backoff = $this->backoff();
        $index = max(0, $this->currentAttempt() - 1);

        return $backoff[$index] ?? ($backoff === [] ? null : end($backoff));
    }
}
