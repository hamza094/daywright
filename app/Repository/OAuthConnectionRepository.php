<?php

declare(strict_types=1);

namespace App\Repository;

use App\DataTransferObjects\OAuth\OAuthTokens;
use App\Models\OAuthConnection;
use App\Models\User;

final class OAuthConnectionRepository
{
    public function hasConnection(User $user, string $provider): bool
    {
        return $this->getTokens($user, $provider) instanceof OAuthTokens;
    }

    public function getTokens(User $user, string $provider): ?OAuthTokens
    {
        $connection = OAuthConnection::query()
            ->where('user_id', $user->getKey())
            ->where('provider', $provider)
            ->first();

        if ($connection) {
            return new OAuthTokens(
                accessToken: $connection->access_token,
                refreshToken: $connection->refresh_token,
                expiresAt: $connection->expires_at->toDateTimeImmutable(),
            );
        }

        return null;
    }

    public function saveTokens(User $user, string $provider, OAuthTokens $tokens): void
    {
        OAuthConnection::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'provider' => $provider,
            ],
            [
                'access_token' => $tokens->accessToken,
                'refresh_token' => $tokens->refreshToken,
                'expires_at' => $tokens->expiresAt,
            ],
        );
    }

    public function clearTokens(User $user, string $provider): void
    {
        OAuthConnection::query()
            ->where('user_id', $user->getKey())
            ->where('provider', $provider)
            ->delete();
    }
}
