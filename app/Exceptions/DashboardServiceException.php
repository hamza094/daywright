<?php

declare(strict_types=1);

namespace App\Exceptions;

use Override;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DashboardServiceException extends ApiException
{
    public function __construct(
        string $message = '',
        private readonly int $status = Response::HTTP_INTERNAL_SERVER_ERROR,
        ?Throwable $previous = null
    ) {
        if ($message === '') {
            $message = $this->defaultMessage();
        }

        parent::__construct(message: $message, previous: $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return 'dashboard_service_error';
    }

    #[Override]
    protected function defaultMessage(): string
    {
        return 'Dashboard service request could not be completed.';
    }
}
