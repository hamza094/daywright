<?php

declare(strict_types=1);

namespace App\Http\Integrations\Zoom\Requests;

use Illuminate\Support\Facades\Cache;
use Saloon\Http\Request;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\LaravelCacheStore;
use Saloon\RateLimitPlugin\Traits\HasRateLimits;

abstract class ZoomRateLimitedRequest extends Request
{
    use HasRateLimits;

    public function __construct(private readonly string $limiterKey) {}

    protected function getLimiterPrefix(): ?string
    {
        return class_basename(static::class).':'.$this->limiterKey;
    }

    /**
     * Zoom HTTP 429 responses must reach ZoomConnector so it can preserve
     * their provider source and Retry-After value. This request still enforces
     * the locally configured request limits.
     */
    protected function getTooManyAttemptsLimiter(): ?Limit
    {
        return null;
    }

    protected function resolveRateLimitStore(): RateLimitStore
    {
        $cacheStore = (string) (config('cache.limiter') ?: config('cache.default'));

        return new LaravelCacheStore(Cache::store($cacheStore));
    }
}
