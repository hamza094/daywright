<?php

declare(strict_types=1);

namespace App\Enums;

enum ApiScope: string
{
    /**
     * API Token Scopes
     *
     * Terminology Note: This codebase uses "scopes" in the domain layer (request, DTO, actions)
     * while Sanctum internally calls them "abilities". The TokenResource serializes them as
     * "abilities" for API responses. This asymmetry is intentional - "scopes" is the user-facing
     * concept, while "abilities" is Sanctum's internal terminology.
     */
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
     * Note: This method is currently unused in production code as validation
     * is handled by Rule::in(ApiScope::values()) in UserTokenRequest.
     * Kept for potential programmatic/service-layer validation use cases.
     *
     * @param  array<int, string>  $scopes
     */
    public static function allValid(array $scopes): bool
    {
        $valid = self::values();

        return collect($scopes)->every(fn (string $scope): bool => in_array($scope, $valid, true));
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function toArray(): array
    {
        return array_map(
            fn (self $scope): array => [
                'value' => $scope->value,
                'label' => $scope->label(),
                'description' => $scope->description(),
            ],
            self::cases()
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::ProjectsRead => 'Projects - Read',
            self::ProjectsWrite => 'Projects - Write',
            self::TeamRead => 'Team - Read',
            self::TeamWrite => 'Team - Write',
            self::AccountRead => 'Account - Read',
            self::AccountWrite => 'Account - Write',
            self::WebhooksWrite => 'Webhooks - Write',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ProjectsRead => 'View projects and related data',
            self::ProjectsWrite => 'Create, update, delete projects',
            self::TeamRead => 'View team members and invitations',
            self::TeamWrite => 'Manage team members and invitations',
            self::AccountRead => 'View account and subscription data',
            self::AccountWrite => 'Manage account and subscription',
            self::WebhooksWrite => 'Manage webhook integrations',
        };
    }
}
