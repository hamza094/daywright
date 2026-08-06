<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BlockApiKeys
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->bearerToken()) {
            throw new AccessDeniedHttpException('API Keys cannot be used for administrative actions.');
        }

        return $next($request);
    }
}
