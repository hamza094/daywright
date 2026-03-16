<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Canonical limit identifiers used by plan config, usage payloads, and enforcement.
 */
enum PlanLimitType: string
{
    case Projects = 'projects';
    case ActiveTasksPerProject = 'active_tasks_per_project';
    case MembersPerProject = 'members_per_project';
    case CreatedMeetings = 'created_meetings';
    case ApiTokens = 'api_tokens';

    public function configKey(): string
    {
        return match ($this) {
            self::Projects => 'max_owned_projects',
            self::ActiveTasksPerProject => 'max_active_tasks_per_project',
            self::MembersPerProject => 'max_members_per_project',
            self::CreatedMeetings => 'max_created_meetings',
            self::ApiTokens => 'max_api_tokens',
        };
    }

    /**
     * Keep legacy error payload keys stable even when the usage payload uses a different name.
     */
    public function exceptionKey(): string
    {
        return match ($this) {
            self::Projects => 'projects',
            self::ActiveTasksPerProject => 'active_tasks_per_project',
            self::MembersPerProject => 'members',
            self::CreatedMeetings => 'meetings',
            self::ApiTokens => 'api_tokens',
        };
    }

    public function messageSubject(): string
    {
        return match ($this) {
            self::Projects => 'projects',
            self::ActiveTasksPerProject => 'active tasks for this project',
            self::MembersPerProject => 'members for this project',
            self::CreatedMeetings => 'created meetings',
            self::ApiTokens => 'API tokens',
        };
    }

    public function requiresProject(): bool
    {
        return match ($this) {
            self::ActiveTasksPerProject, self::MembersPerProject => true,
            self::Projects, self::CreatedMeetings, self::ApiTokens => false,
        };
    }
}
