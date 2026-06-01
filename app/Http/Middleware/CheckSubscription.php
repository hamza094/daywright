<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Subscription\SubscriptionRequiredException;
use Closure;
use Illuminate\Auth\AuthenticationException;
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
            throw new AuthenticationException('Unauthenticated.');
        }

        if ($user->isSubscribed() || $user->isOnTrial()) {
            return $next($request);
        }

        throw new SubscriptionRequiredException;
    }
}
