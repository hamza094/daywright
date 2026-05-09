<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations\Zoom;

use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ZoomException extends ApiException
{
    public function status(): int
    {
        return Response::HTTP_SERVICE_UNAVAILABLE;
    }

    public function errorCode(): string
    {
        return 'zoom_unavailable';
    }

    public function publicMessage(): string
    {
        return $this->defaultMessage();
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(Request $request): array
    {
        return [
            'provider' => 'zoom',
        ];
    }

    protected function defaultMessage(): string
    {
        return 'Zoom service is temporarily unavailable.';
    }
}
