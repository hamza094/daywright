<?php

declare(strict_types=1);

namespace App\Exceptions\Paddle;

use App\Exceptions\Integrations\ExternalServiceUnavailableException;
use Illuminate\Http\Request;
use Override;
use Symfony\Component\HttpFoundation\Response;

class PaddleUnavailableException extends ExternalServiceUnavailableException
{
    public function __construct(
        string $message = 'Paddle service is currently unavailable. Please try again later.'
    ) {
        parent::__construct($message, Response::HTTP_SERVICE_UNAVAILABLE);
    }

    #[Override]
    public function errorCode(): string
    {
        return 'paddle_unavailable';
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
}
