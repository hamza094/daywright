<?php

declare(strict_types=1);

namespace App\Exceptions\Paddle;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Debug\ShouldntReport;
use Override;
use Symfony\Component\HttpFoundation\Response;

class ActiveOperationConflictException extends ApiException implements ShouldntReport
{
    public function __construct(
        string $message = 'Another subscription operation is currently in progress.'
    ) {
        parent::__construct($message);
    }

    #[Override]
    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }

    #[Override]
    public function errorCode(): string
    {
        return 'active_operation_conflict';
    }
}
