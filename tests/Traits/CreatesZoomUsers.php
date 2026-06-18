<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\User;
use DateTimeInterface;

trait CreatesZoomUsers
{
    protected function createZoomUser(DateTimeInterface $expireAt): User
    {
        $user = User::factory()->create();

        $user->oauthConnections()->create([
            'provider' => 'zoom',
            'access_token' => 'access-token-here',
            'refresh_token' => 'refresh-token-here',
            'expires_at' => $expireAt,
        ]);

        return $user;
    }
}
