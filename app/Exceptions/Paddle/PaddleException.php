<?php

declare(strict_types=1);

namespace App\Exceptions\Paddle;

use App\Exceptions\Integrations\ExternalServiceUnavailableException;
use Illuminate\Http\Request;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PaddleException extends ExternalServiceUnavailableException
{
    public function __construct(
        string $message = 'Payment request could not be completed.',
        int $status = Response::HTTP_SERVICE_UNAVAILABLE,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    #[Override]
    public function errorCode(): string
    {
        return 'payment_error';
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function meta(Request $request): array
    {
        return [
            'provider' => 'paddle',
        ];
    }

    #[Override]
    protected function defaultMessage(): string
    {
        return 'Payment request could not be completed.';
    }
}
