<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations\Zoom;

use Illuminate\Http\Request;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ZoomRateLimitException extends ZoomException
{
    public const string SOURCE_APPLICATION = 'application';

    public const string SOURCE_ZOOM = 'zoom';

    private readonly ?int $retryAfterSeconds;

    public function __construct(
        ?int $retryAfterSeconds = null,
        string $message = '',
        ?Throwable $previous = null,
        private readonly string $source = self::SOURCE_ZOOM,
    ) {
        $this->retryAfterSeconds = $retryAfterSeconds !== null && $retryAfterSeconds > 0
            ? $retryAfterSeconds
            : null;
        parent::__construct($message ?: 'Zoom rate limit exceeded.', 0, $previous);
    }

    public function status(): int
    {
        return Response::HTTP_TOO_MANY_REQUESTS;
    }

    public function errorCode(): string
    {
        return 'zoom_rate_limit';
    }

    #[Override]
    public function publicMessage(): string
    {
        $message = $this->source === self::SOURCE_APPLICATION
            ? 'This app is limiting Zoom requests. Please retry later.'
            : 'Zoom is limiting requests to its API. Please retry later.';

        return $this->retryAfterSeconds === null
            ? $message
            : $message." Try again in {$this->retryAfterSeconds} seconds.";
    }

    #[Override]
    public function meta(Request $request): array
    {
        return [
            'provider' => 'zoom',
            'reason' => 'rate_limit',
            'rate_limit_source' => $this->source,
            ...($this->retryAfterSeconds !== null
                ? ['retry_after_seconds' => $this->retryAfterSeconds]
                : []),
            ...parent::meta($request),
        ];
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    #[Override]
    public function headers(): array
    {
        $retryAfter = $this->retryAfterSeconds();

        if ($retryAfter === null) {
            return [];
        }

        return [
            'Retry-After' => (string) $retryAfter,
        ];
    }
}
