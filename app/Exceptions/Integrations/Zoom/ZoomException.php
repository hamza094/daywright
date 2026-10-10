<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations\Zoom;

use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use Override;
use Symfony\Component\HttpFoundation\Response;

class ZoomException extends ApiException
{
    /**
     * @var array<string, mixed>
     */
    private array $context = [];

    public function status(): int
    {
        return Response::HTTP_SERVICE_UNAVAILABLE;
    }

    public function errorCode(): string
    {
        return 'zoom_unavailable';
    }

    #[Override]
    public function publicMessage(): string
    {
        return $this->defaultMessage();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function meta(Request $request): array
    {
        return [
            'provider' => 'zoom',
            ...$this->context,
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function headers(): array
    {
        $retryAfter = $this->context['retry_after_seconds'] ?? null;

        if (! is_int($retryAfter) || $retryAfter <= 0) {
            return [];
        }

        return [
            'Retry-After' => (string) $retryAfter,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): static
    {
        $this->context = [...$this->context, ...$context];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    #[Override]
    protected function defaultMessage(): string
    {
        return 'Zoom service is temporarily unavailable.';
    }
}
