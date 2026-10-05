<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations\Zoom;

use Illuminate\Http\Request;
use Override;
use Symfony\Component\HttpFoundation\Response;

final class ZoomRateLimitException extends ZoomException
{
    private readonly ?int $retryAfterSeconds;

    public function __construct(?int $retryAfterSeconds = null, string $message = '')
    {
        $this->retryAfterSeconds = $retryAfterSeconds;
        parent::__construct($message ?: 'Zoom rate limit exceeded.');
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
        return 'Too many requests to Zoom. Please retry later.';
    }

    #[Override]
    public function meta(Request $request): array
    {
        return [
            'provider' => 'zoom',
            'reason' => 'rate_limit',
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
