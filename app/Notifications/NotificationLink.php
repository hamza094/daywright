<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\URL;

final class NotificationLink
{
    private const DEFAULT_VERSION = 'v1';

    public static function project(string $projectSlug, bool $absolute = false, ?string $version = null): string
    {
        return route(self::routeName('projects.show', $version), ['project' => $projectSlug], $absolute);
    }

    public static function verification(User $user, DateTimeInterface $expiration, ?string $version = null): string
    {
        return URL::temporarySignedRoute(
            self::routeName('verification.verify', $version),
            $expiration,
            [
                'user' => $user->uuid,
                // sha1 is expected by Laravel's verification flow; the URL itself is HMAC-signed via temporarySignedRoute. NOSONAR
                'hash' => sha1((string) $user->getEmailForVerification()),
            ]
        );
    }

    private static function routeName(string $suffix, ?string $version = null): string
    {
        return sprintf('api.%s.%s', $version ?? self::DEFAULT_VERSION, $suffix);
    }
}
