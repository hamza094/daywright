<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations\Zoom;

use Symfony\Component\HttpFoundation\Response;

class NotFoundException extends ZoomUserErrorException
{
    public function status(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function errorCode(): string
    {
        return 'zoom_not_found';
    }

    protected function defaultMessage(): string
    {
        return 'Zoom resource not found.';
    }
}
