<?php

declare(strict_types=1);

namespace Tests\Support\Zoom;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ZoomOAuthTestHelper
{
    public static function createAuthorizationState(string $state, User $user, string $verifier): void
    {
        Cache::put('oauth:zoom:authorization:'.$state, [
            'user_id' => $user->getKey(),
            'code_verifier' => $verifier,
        ], now()->addMinutes(10));
    }

    public static function assertTokensSaved(User $user, string $expectedAccessToken, string $expectedRefreshToken): void
    {
        $tokens = app(\App\Repository\OAuthConnectionRepository::class)->getTokens($user, 'zoom');

        \PHPUnit\Framework\assertNotNull($tokens, 'Tokens should be saved for user');
        \PHPUnit\Framework\assertEquals($expectedAccessToken, $tokens->accessToken, 'Access token should match expected value');
        \PHPUnit\Framework\assertEquals($expectedRefreshToken, $tokens->refreshToken, 'Refresh token should match expected value');
    }

    public static function assertNoTokensSaved(User $user): void
    {
        $tokens = app(\App\Repository\OAuthConnectionRepository::class)->getTokens($user, 'zoom');

        \PHPUnit\Framework\assertNull($tokens, 'No tokens should be saved for user');
    }
}
