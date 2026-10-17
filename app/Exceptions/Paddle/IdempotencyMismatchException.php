<?php

declare(strict_types=1);

namespace App\Exceptions\Paddle;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Debug\ShouldntReport;
use Override;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMismatchException extends ApiException implements ShouldntReport
{
    public function __construct(
        string $message = 'Idempotency key was previously used with different request parameters.'
    ) {
        parent::__construct($message);
    }

    #[Override]
    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    #[Override]
    public function errorCode(): string
    {
        return 'idempotency_fingerprint_mismatch';
    }
}
