<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\Models\User;

final class ZoomLimiter
{
    public const PREFIX = 'zoom:user:';

    public static function forUser(User $user): string
    {
        return self::PREFIX.$user->getKey();
    }

    public static function forUserId(int $id): string
    {
        return self::PREFIX.$id;
    }
}
