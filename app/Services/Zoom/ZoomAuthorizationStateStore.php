<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\DataTransferObjects\Zoom\AuthorizationRedirectDetails;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ZoomAuthorizationStateStore
{
    private const string INVALID_STATE_MESSAGE = 'Zoom authorization session is invalid or expired.';

    private const string VERIFIER_CACHE_KEY_PREFIX = 'oauth:zoom:';

    private const string CALLBACK_LOCK_KEY_PREFIX = 'lock:oauth:zoom:callback:';

    private const int CALLBACK_LOCK_SECONDS = 5;

    private const int VERIFIER_TTL_MINUTES = 10;

    public function storeRedirectDetails(AuthorizationRedirectDetails $redirectDetails): void
    {
        Cache::put(
            $this->verifierCacheKey($redirectDetails->state),
            $redirectDetails->codeVerifier,
            now()->addMinutes(self::VERIFIER_TTL_MINUTES),
        );
    }

    public function takeVerifier(string $state): string
    {
        $codeVerifier = Cache::lock($this->callbackLockKey($state), self::CALLBACK_LOCK_SECONDS)
            ->get(fn (): mixed => $this->pullVerifier($state));

        if (! is_string($codeVerifier) || $codeVerifier === '') {
            throw new HttpException(Response::HTTP_BAD_REQUEST, self::INVALID_STATE_MESSAGE);
        }

        return $codeVerifier;
    }

    private function pullVerifier(string $state): mixed
    {
        return Cache::pull($this->verifierCacheKey($state));
    }

    private function verifierCacheKey(string $state): string
    {
        return self::VERIFIER_CACHE_KEY_PREFIX.$state;
    }

    private function callbackLockKey(string $state): string
    {
        return self::CALLBACK_LOCK_KEY_PREFIX.$state;
    }
}
