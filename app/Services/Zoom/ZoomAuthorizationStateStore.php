<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\DataTransferObjects\Zoom\AuthorizationRedirectDetails;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ZoomAuthorizationStateStore
{
    private const string INVALID_STATE_MESSAGE = 'Zoom authorization session is invalid or expired.';

    private const string AUTHORIZATION_CACHE_KEY_PREFIX = 'oauth:zoom:authorization:';

    private const string CALLBACK_LOCK_KEY_PREFIX = 'lock:oauth:zoom:callback:';

    private const int CALLBACK_LOCK_SECONDS = 5;

    private const int VERIFIER_TTL_MINUTES = 10;

    public function store(
        AuthorizationRedirectDetails $redirectDetails,
        User $user,
    ): void {
        Cache::put(
            $this->authorizationCacheKey($redirectDetails->state),
            [
                'user_id' => $user->getKey(),
                'code_verifier' => $redirectDetails->codeVerifier,
            ],
            now()->addMinutes(self::VERIFIER_TTL_MINUTES),
        );
    }

    public function consume(string $state, User $user): string
    {
        $authorization = Cache::lock(
            $this->callbackLockKey($state),
            self::CALLBACK_LOCK_SECONDS,
        )->get(
            fn (): mixed => Cache::pull(
                $this->authorizationCacheKey($state)
            ),
        );

        if (! is_array($authorization)) {
            $this->invalidState();
        }

        if (($authorization['user_id'] ?? null) !== $user->getKey()) {
            $this->invalidState();
        }

        $codeVerifier = $authorization['code_verifier'] ?? null;

        if (! is_string($codeVerifier) || $codeVerifier === '') {
            $this->invalidState();
        }

        return $codeVerifier;
    }

    public function forget(string $state): void
    {
        Cache::forget($this->authorizationCacheKey($state));
    }

    private function invalidState(): never
    {
        throw new HttpException(Response::HTTP_BAD_REQUEST, self::INVALID_STATE_MESSAGE);
    }

    private function authorizationCacheKey(string $state): string
    {
        return self::AUTHORIZATION_CACHE_KEY_PREFIX.$state;
    }

    private function callbackLockKey(string $state): string
    {
        return self::CALLBACK_LOCK_KEY_PREFIX.$state;
    }
}
