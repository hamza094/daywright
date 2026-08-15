<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireFirstPartyAuth
{
    /**
     * Handle an incoming request.
     *
     * Ensures the request is authenticated via web session or official mobile app token.
     * Blocks third-party developer API keys from accessing sensitive operations like password changes.
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

        /** @var \Laravel\Sanctum\PersonalAccessToken|TransientToken|null $currentToken */
        $currentToken = $user->currentAccessToken();

        // Allow session-based access (no token), TransientToken (SPA sessions), and wildcard tokens (official mobile apps)
        if ($currentToken === null || $currentToken instanceof TransientToken || $currentToken->can('*')) {
            return $next($request);
        }

        // Reject third-party API keys
        return response()->json([
            'message' => 'This operation is restricted to first-party clients only. Web sessions and official mobile apps are allowed.',
            'code' => 'forbidden',
            'errors' => [],
            'meta' => [],
        ], Response::HTTP_FORBIDDEN);
    }
}
