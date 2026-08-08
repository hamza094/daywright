<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

final class MaxTokensExceededException extends ApiException
{
    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }

    public function errorCode(): string
    {
        return 'max_tokens_exceeded';
    }

    public function publicMessage(): string
    {
        return 'You have reached the maximum limit of 5 API tokens. Please delete an existing token before creating a new one.';
    }
}
