<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations\Zoom;

use Illuminate\Contracts\Debug\ShouldntReport;
use Override;
use Symfony\Component\HttpFoundation\Response;

class ZoomUserErrorException extends ZoomException implements ShouldntReport
{
    #[Override]
    public function status(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    #[Override]
    public function errorCode(): string
    {
        return 'zoom_error';
    }

    #[Override]
    public function publicMessage(): string
    {
        $message = trim($this->getMessage());

        if ($message === 'User is not connected to Zoom.') {
            return $message;
        }

        return $this->defaultMessage();
    }

    #[Override]
    protected function defaultMessage(): string
    {
        return 'Zoom request failed.';
    }
}
