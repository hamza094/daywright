<?php

declare(strict_types=1);

namespace App\Enums\Subscription;

use Illuminate\Database\Eloquent\Builder;

/**
 * Canonical limit identifiers used by plan config, usage payloads, and enforcement.
 */
enum PlanLimitType: string
{
    case Projects = 'projects';
    case TasksPerProject = 'tasks_per_project';
    case MembersPerProject = 'members_per_project';
    case CreatedMeetings = 'created_meetings';
    case ApiTokens = 'api_tokens';

    private const SCOPE_ACCOUNT = 'account';

    private const SCOPE_PROJECT = 'project';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * @return array<self>
     */
    public static function accountTypes(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (self $type): bool => ! $type->requiresProject(),
        ));
    }

    /**
     * @return array<self>
     */
    public static function projectTypes(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (self $type): bool => $type->requiresProject(),
        ));
    }

    /**
     * @return array<int|string, string|callable>
     */
    public static function accountCountLoaders(): array
    {
        return self::countLoadersFor(self::accountTypes());
    }

    /**
     * @return array<int|string, string|callable>
     */
    public static function projectCountLoaders(): array
    {
        return self::countLoadersFor(self::projectTypes());
    }

    public function configKey(): string
    {
        return $this->definition()['configKey'];
    }

    /**
     * Keep legacy error payload keys stable even when the usage payload uses a different name.
     */
    public function exceptionKey(): string
    {
        return $this->definition()['exceptionKey'];
    }

    public function messageSubject(): string
    {
        return $this->definition()['messageSubject'];
    }

    public function displayLabel(): string
    {
        return $this->definition()['displayLabel'];
    }

    public function loadedCountAttribute(): string
    {
        return $this->definition()['loadedCountAttribute'];
    }

    /**
     * @return array<int|string, string|callable>
     */
    public function countLoaders(): array
    {
        return $this->definition()['countLoaders'];
    }

    public function scope(): string
    {
        return $this->definition()['scope'];
    }

    public function requiresProject(): bool
    {
        return $this->scope() === self::SCOPE_PROJECT;
    }

    /**
     * @param  array<int, self>  $types
     * @return array<int|string, string|callable>
     */
    private static function countLoadersFor(array $types): array
    {
        return array_reduce(
            $types,
            static fn (array $loaders, self $type): array => array_merge($loaders, $type->countLoaders()),
            [],
        );
    }

    /**
     * @return array{configKey: string, exceptionKey: string, messageSubject: string, displayLabel: string, loadedCountAttribute: string, countLoaders: array<int|string, string|callable>, scope: string}
     */
    private function definition(): array
    {
        return match ($this) {
            self::Projects => [
                'configKey' => 'max_owned_projects',
                'exceptionKey' => 'projects',
                'messageSubject' => 'projects',
                'displayLabel' => 'Projects',
                'loadedCountAttribute' => 'projects_count',
                'countLoaders' => ['projects'],
                'scope' => self::SCOPE_ACCOUNT,
            ],
            self::TasksPerProject => [
                'configKey' => 'max_tasks_per_project',
                'exceptionKey' => 'tasks_per_project',
                'messageSubject' => 'tasks for this project',
                'displayLabel' => 'Tasks',
                'loadedCountAttribute' => 'active_tasks_count',
                'countLoaders' => [
                    'tasks as active_tasks_count' => static fn (Builder $query): Builder => $query->whereNull('deleted_at'),
                ],
                'scope' => self::SCOPE_PROJECT,
            ],
            self::MembersPerProject => [
                'configKey' => 'max_members_per_project',
                'exceptionKey' => 'members',
                'messageSubject' => 'members for this project',
                'displayLabel' => 'Members',
                'loadedCountAttribute' => 'active_members_count',
                'countLoaders' => [
                    'activeMembers as active_members_count' => static fn (Builder $query): Builder => $query,
                ],
                'scope' => self::SCOPE_PROJECT,
            ],
            self::CreatedMeetings => [
                'configKey' => 'max_created_meetings',
                'exceptionKey' => 'meetings',
                'messageSubject' => 'created meetings',
                'displayLabel' => 'Created meetings',
                'loadedCountAttribute' => 'meetings_count',
                'countLoaders' => [
                    'meetings as meetings_count' => static fn (Builder $query): Builder => $query->where('sync_status', 'active'),
                ],
                'scope' => self::SCOPE_ACCOUNT,
            ],
            self::ApiTokens => [
                'configKey' => 'max_api_tokens',
                'exceptionKey' => 'api_tokens',
                'messageSubject' => 'API tokens',
                'displayLabel' => 'API tokens',
                'loadedCountAttribute' => 'tokens_count',
                'countLoaders' => ['tokens'],
                'scope' => self::SCOPE_ACCOUNT,
            ],
        };
    }
}
