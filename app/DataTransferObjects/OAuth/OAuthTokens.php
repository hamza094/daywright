<?php

declare(strict_types=1);

namespace App\DataTransferObjects\OAuth;

use DateTimeImmutable;

final class OAuthTokens
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public DateTimeImmutable $expiresAt,
    ) {}
}
