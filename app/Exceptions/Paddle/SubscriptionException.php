<?php

declare(strict_types=1);

namespace App\Exceptions\Paddle;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Debug\ShouldntReport;
use Override;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionException extends ApiException implements ShouldntReport
{
    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }

    public function errorCode(): string
    {
        return 'subscription_conflict';
    }

    #[Override]
    protected function defaultMessage(): string
    {
        return 'Subscription request could not be completed.';
    }
}
