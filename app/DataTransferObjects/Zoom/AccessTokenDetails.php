<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

use DateTimeImmutable;

final readonly class AccessTokenDetails
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public DateTimeImmutable $expiresAt,
    ) {}
}
