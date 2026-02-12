<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserLogin;
use Illuminate\Support\Facades\Http;
use Torann\GeoIP\Facades\GeoIP;

use function Illuminate\Support\defer;

/**
 * Persist a user's timezone after login.
 *
 * The lookup is performed deferred (after the response) to avoid blocking
 * the login flow. If an IP is provided on the event it will be used for
 * GeoIP lookup; otherwise the listener will attempt to resolve the
 * server's public IP and use that as a fallback.
 */
class SaveUserTimezone
{
    public function handle(UserLogin $event): void
    {
        if ($event->user->timezone) {
            return;
        }

        $user = $event->user;
        $ip = $event->ip;

        defer(function () use ($user, $ip): void {

            if ($user->timezone) {
                return;
            }

            $timezone = $this->resolveTimezone($ip);

            if (! $timezone) {
                return;
            }

            $user->timezone = $timezone;
            $user->save();
        });
    }

    private function resolveTimezone(?string $ip = null): ?string
    {
        $resolvedIp = $ip ?: $this->resolvePublicIp();

        if (! $resolvedIp) {
            return null;
        }

        return rescue(fn () => GeoIP::getLocation($resolvedIp)->timezone ?? null, null, true);
    }

    private function resolvePublicIp(): ?string
    {
        $response = rescue(fn () => Http::timeout(2)->get('https://ipecho.net/plain'), null, true);

        $ip = $response?->successful() ? trim($response->body()) : null;

        return $ip && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }
}
