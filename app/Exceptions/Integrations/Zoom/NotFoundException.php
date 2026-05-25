<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations\Zoom;

use Override;
use Symfony\Component\HttpFoundation\Response;

class NotFoundException extends ZoomUserErrorException
{
    #[Override]
    public function status(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    #[Override]
    public function errorCode(): string
    {
        return 'zoom_not_found';
    }

    #[Override]
    protected function defaultMessage(): string
    {
        return 'Zoom resource not found.';
    }
}
