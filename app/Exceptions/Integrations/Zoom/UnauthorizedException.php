<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations\Zoom;

use Symfony\Component\HttpFoundation\Response;

class UnauthorizedException extends ZoomUserErrorException
{
    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }

    public function errorCode(): string
    {
        return 'zoom_forbidden';
    }

    protected function defaultMessage(): string
    {
        return 'Zoom access is forbidden.';
    }
}
