<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\User;
use DateTimeInterface;

trait CreatesZoomUsers
{
    protected function createZoomUser(DateTimeInterface $expireAt): User
    {
        return User::factory()->create([
            'zoom_access_token' => 'access-token-here',
            'zoom_refresh_token' => 'refresh-token-here',
            'zoom_expires_at' => $expireAt,
        ]);
    }
}
