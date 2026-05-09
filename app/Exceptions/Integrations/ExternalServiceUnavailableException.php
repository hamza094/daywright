<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations;

use App\Exceptions\ApiException;
use App\Exceptions\Support\ApiErrorFormatter;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ExternalServiceUnavailableException extends ApiException
{
    public function __construct(
        string $message = 'The service is temporarily unavailable.',
        private readonly int $status = Response::HTTP_INTERNAL_SERVER_ERROR,
        ?Throwable $previous = null,
    ) {
        parent::__construct(message: $message, previous: $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return ApiErrorFormatter::defaultCodeForStatus($this->status());
    }
}
