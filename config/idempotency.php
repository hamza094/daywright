<?php

declare(strict_types=1);

use WendellAdriel\Idempotency\Enums\IdempotencyScope;

return [
    /*
    |--------------------------------------------------------------------------
    | Idempotency TTL
    |--------------------------------------------------------------------------
    |
    | Number of seconds an idempotency key should remain valid.
    |
    */
    'ttl' => env('IDEMPOTENCY_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Idempotency Required Header
    |--------------------------------------------------------------------------
    |
    | Determine whether requests must include the configured idempotency
    | header. When disabled, requests without the header pass through.
    |
    */
    'required' => env('IDEMPOTENCY_REQUIRED', true),

    /*
    |--------------------------------------------------------------------------
    | Idempotency Scope
    |--------------------------------------------------------------------------
    |
    | Supported values are: user, ip, and global. The user scope falls back
    | to the request IP address when no authenticated user is available.
    |
    */
    'scope' => env('IDEMPOTENCY_SCOPE', IdempotencyScope::User->value),

    /*
    |--------------------------------------------------------------------------
    | Idempotency Header
    |--------------------------------------------------------------------------
    |
    | This header will be inspected for the client-provided idempotency key.
    |
    */
    'header' => env('IDEMPOTENCY_HEADER', 'Idempotency-Key'),
];
