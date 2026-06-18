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
