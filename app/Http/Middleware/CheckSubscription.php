<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * Active subscribers, grace-period users, and trial users retain access.
     * Once the grace period ends, premium routes are blocked by this middleware.
     *
     * @param  Closure(Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     */
    public function handle(Request $request, Closure $next): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->isSubscribed() || $user->isOnTrial()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Access denied. An active subscription is required to perform this action.',
            'error_type' => 'subscription_required',
            'upgrade_required' => true,
        ], 403);
    }
}
