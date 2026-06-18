<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations\Zoom;

use Illuminate\Contracts\Debug\ShouldntReport;
use Override;
use Symfony\Component\HttpFoundation\Response;

class ZoomUserErrorException extends ZoomException implements ShouldntReport
{
    private const string USER_NOT_CONNECTED = 'User is not connected to Zoom.';

    private const string RECONNECT_REQUIRED = 'Zoom account connection needs to be re-authorized.';

    #[Override]
    public function status(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    #[Override]
    public function errorCode(): string
    {
        if (trim($this->getMessage()) === self::RECONNECT_REQUIRED) {
            return 'zoom_reconnect_required';
        }

        return 'zoom_error';
    }

    #[Override]
    public function publicMessage(): string
    {
        $message = trim($this->getMessage());

        if (in_array($message, [self::USER_NOT_CONNECTED, self::RECONNECT_REQUIRED], true)) {
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
