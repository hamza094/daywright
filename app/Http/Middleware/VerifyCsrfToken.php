<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * Note: Session logout routes are exempt from CSRF token verification because:
     * 1. Session destruction is an idempotent cleanup operation guarded by SameSite=Lax cookies.
     * 2. Dormant SPA tabs with expired CSRF tokens must still be able to successfully terminate
     *    the authenticated server session without throwing HTTP 419 token mismatch errors.
     *
     * @var array
     */
    protected $except = [
        'paddle/*',
        'api/*/session/logout',
        'session/logout',
    ];
}
