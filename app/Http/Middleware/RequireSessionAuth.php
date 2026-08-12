<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireSessionAuth
{
    /**
     * Handle an incoming request.
     *
     * Ensures the request is authenticated via session, not via API token.
     * This prevents token chaining by requiring session-based authentication
     * for sensitive operations like token management, 2FA management and admin actions.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => 'Authentication is required.',
                'code' => 'unauthenticated',
                'errors' => [],
                'meta' => [],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $currentToken = $user->currentAccessToken();

        // Allow session-based access (no token) and TransientToken (SPA sessions)
        if ($currentToken === null || $currentToken instanceof TransientToken) {
            return $next($request);
        }

        // Reject API token access
        return response()->json([
            'message' => 'This operation is strictly reserved for the web dashboard. Please use session-based authentication.',
            'code' => 'forbidden',
            'errors' => [],
            'meta' => [],
        ], Response::HTTP_FORBIDDEN);
    }
}
