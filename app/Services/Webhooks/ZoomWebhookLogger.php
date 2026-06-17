<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Services\Zoom\ZoomLogContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

use function Safe\preg_replace;

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
        $params = new WebhookLogParameters($operation, $meetingId, $requestId, $userIdentifier, $context);
        $this->logWebhook('info', 'zoom_webhook_processed', $params);
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
        $params = new WebhookLogParameters($operation, $meetingId, $requestId, $userIdentifier, [
            'reason' => $reason,
            ...$context,
        ]);
        $this->logWebhook('info', 'zoom_webhook_ignored', $params);
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
        $params = new WebhookLogParameters($operation, $meetingId, $requestId, $userIdentifier, [
            ...$this->sanitizeContext($context),
            'exception' => $exception::class,
            'message' => Str::limit($this->sanitize($exception->getMessage()), 1000),
        ]);
        $this->logWebhook('warning', 'zoom_webhook_retry_scheduled', $params);
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
        $params = new WebhookLogParameters($operation, $meetingId, $requestId, $userIdentifier, [
            ...$this->sanitizeContext($context),
            'exception' => $exception::class,
            'message' => Str::limit($this->sanitize($exception->getMessage()), 1000),
        ]);
        $this->logWebhook('error', 'zoom_webhook_failed', $params, 'zoom_webhook_failed');
    }

    private function logWebhook(
        string $level,
        string $event,
        WebhookLogParameters $params,
        string $channel = 'zoom'
    ): void {
        $logContext = ZoomLogContext::forWebhook(
            operation: $params->operation,
            meetingId: $params->meetingId,
            requestId: $params->requestId,
            userId: $params->userIdentifier,
            context: $params->context,
        );

        $this->writeLog($level, $event, $channel, $logContext);
    }

    /**
     * @param  array<string, mixed>  $logContext
     */
    private function writeLog(string $level, string $event, string $channel, array $logContext): void
    {
        Log::channel($channel)->{$level}($event, $logContext);
    }

    private function sanitize(string $message): string
    {
        $patterns = [
            '/access_token["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
            '/refresh_token["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
            '/authorization["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
            '/webhook_secret["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
            '/start_url["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
            '/zak["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
            '/token["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
            '/secret["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
            '/password["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
            '/api[_-]?key["\']?\s*[:=]\s*["\']?[^"\',\s}]+/i',
        ];

        foreach ($patterns as $pattern) {
            $message = preg_replace($pattern, '[REDACTED]', (string) $message);
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($value)) {
                $context[$key] = $this->sanitize($value);
            }
        }

        return $context;
    }
}

/**
 * @internal
 */
final readonly class WebhookLogParameters
{
    public function __construct(
        public string $operation,
        public int|string $meetingId,
        public ?string $requestId,
        public int|string|null $userIdentifier,
        /** @var array<string, mixed> */
        public array $context,
    ) {}
}
