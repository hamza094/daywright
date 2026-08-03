<?php

declare(strict_types=1);

namespace App\Enums;

enum ApiScope: string
{
    case ProjectsRead = 'projects:read';
    case ProjectsWrite = 'projects:write';
    case TeamRead = 'team:read';
    case TeamWrite = 'team:write';
    case AccountRead = 'account:read';
    case AccountWrite = 'account:write';
    case WebhooksWrite = 'webhooks:write';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Validate that all provided scope strings are valid.
     *
     * @param  array<int, string>  $scopes
     */
    public static function allValid(array $scopes): bool
    {
        $valid = self::values();

        return collect($scopes)->every(fn (string $scope) => in_array($scope, $valid, true));
    }
}
