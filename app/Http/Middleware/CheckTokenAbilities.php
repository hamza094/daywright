<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities as SanctumCheckAbilities;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenAbilities
{
    public function __construct(
        private readonly SanctumCheckAbilities $sanctumMiddleware
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        // Only check abilities for token-based authentication
        // Session-based requests rely on policies (can: middleware)
        if ($request->bearerToken()) {
            return $this->sanctumMiddleware->handle($request, $next, ...$abilities);
        }

        return $next($request);
    }
}
