<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array|string
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    public function __construct()
    {
        // Behind AWS ALB / Cloudflare / any reverse proxy, forwarded headers
        // must be trusted or Laravel treats requests as HTTP. Set
        // TRUSTED_PROXIES to specific IPs/CIDRs in production; '*' trusts all.
        $proxies = (string) config('app.trusted_proxies', '*');
        $this->proxies = $proxies === '*' ? '*' : array_map(trim(...), explode(',', $proxies));
    }
}
