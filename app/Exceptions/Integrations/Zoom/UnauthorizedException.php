<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations\Zoom;

use Override;
use Symfony\Component\HttpFoundation\Response;

class UnauthorizedException extends ZoomUserErrorException
{
    #[Override]
    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }

    #[Override]
    public function errorCode(): string
    {
        return 'zoom_forbidden';
    }

    #[Override]
    protected function defaultMessage(): string
    {
        return 'Zoom access is forbidden.';
    }
}
