<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Exceptions\Support\ErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;
use Override;
use Symfony\Component\HttpFoundation\Response;

final class TaskNotTrashedException extends ApiException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct($this->defaultMessage());
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }

    public function errorCode(): string
    {
        return ErrorCode::TASK_NOT_TRASHED;
    }

    #[Override]
    protected function defaultMessage(): string
    {
        return 'Task must be trashed to perform this action';
    }
}
