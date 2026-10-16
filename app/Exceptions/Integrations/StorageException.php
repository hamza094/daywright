<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations;

use App\Exceptions\ApiException;
use App\Exceptions\Support\ErrorCode;
use Illuminate\Http\Request;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class StorageException extends ApiException
{
    public function __construct(
        string $message = 'Storage request could not be completed.',
        private readonly string $provider = 's3',
        ?Throwable $previous = null,
    ) {
        parent::__construct(message: $message, previous: $previous);
    }

    #[Override]
    public function status(): int
    {
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    #[Override]
    public function errorCode(): string
    {
        return ErrorCode::STORAGE_ERROR;
    }

    #[Override]
    public function publicMessage(): string
    {
        return 'Storage request could not be completed.';
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function meta(Request $request): array
    {
        return [
            'provider' => $this->provider,
        ];
    }
}
