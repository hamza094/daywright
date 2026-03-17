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
     * Grace-period users retain full Pro access until the grace period ends.
     * Post-grace enforcement of Free limits is handled by PlanLimitService
     * assertions in creation/increase flows, not by this middleware.
     *
     * @param  Closure(Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     */
    public function handle(Request $request, Closure $next): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if ($user->hasSubscriptionRecord() || $user->isOnTrial()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Access denied. An active subscription is required to perform this action.',
            'error_type' => 'subscription_required',
            'upgrade_required' => true,
        ], 403);
    }
}
