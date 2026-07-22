<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

final readonly class AuthorizationCallbackDetails
{
    public function __construct(
        public string $authorizationCode,
        public string $state,
        public string $codeVerifier,
    ) {}
}
