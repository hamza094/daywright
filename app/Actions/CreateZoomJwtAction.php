<?php

declare(strict_types=1);

namespace App\Actions;

use Firebase\JWT\JWT;

final class CreateZoomJwtAction
{
    public function __construct(
        private readonly ?string $sdkKey = null,
        private readonly ?string $sdkSecret = null,
    ) {}

    public function execute(int|string $meetingNumber, int $role): string
    {
        $iat = time() - 30;
        $exp = $iat + 60 * 60 * 2;
        $sdkKey = $this->sdkKey ?? (string) config('services.zoom.client_id');
        $sdkSecret = $this->sdkSecret ?? (string) config('services.zoom.client_secret');

        $payload = [
            'sdkKey' => $sdkKey,
            'appKey' => $sdkKey,
            'mn' => $meetingNumber,
            'role' => $role,
            'iat' => $iat,
            'exp' => $exp,
            'tokenExp' => $exp,
        ];

        return JWT::encode($payload, $sdkSecret, 'HS256');
    }
}
