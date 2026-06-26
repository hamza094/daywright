<?php

declare(strict_types=1);

namespace Tests\Unit\Enums\Subscription;

use App\Enums\Subscription\PlanLimitType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PlanLimitTypeTest extends TestCase
{
    /**
     * @return array<string, array{0: PlanLimitType, 1: array{value: string, config_key: string, exception_key: string, message_subject: string, display_label: string, loaded_count_attribute: string, count_loader_keys: array<int, string>, scope: string, requires_project: bool}}>
     */
    public static function metadataProvider(): array
    {
        return [
            'projects' => [
                PlanLimitType::Projects,
                [
                    'value' => 'projects',
                    'config_key' => 'max_owned_projects',
                    'exception_key' => 'projects',
                    'message_subject' => 'projects',
                    'display_label' => 'Projects',
                    'loaded_count_attribute' => 'projects_count',
                    'count_loader_keys' => ['projects'],
                    'scope' => 'account',
                    'requires_project' => false,
                ],
            ],
            'tasks per project' => [
                PlanLimitType::TasksPerProject,
                [
                    'value' => 'tasks_per_project',
                    'config_key' => 'max_tasks_per_project',
                    'exception_key' => 'tasks_per_project',
                    'message_subject' => 'tasks for this project',
                    'display_label' => 'Tasks',
                    'loaded_count_attribute' => 'active_tasks_count',
                    'count_loader_keys' => ['tasks as active_tasks_count'],
                    'scope' => 'project',
                    'requires_project' => true,
                ],
            ],
            'members per project' => [
                PlanLimitType::MembersPerProject,
                [
                    'value' => 'members_per_project',
                    'config_key' => 'max_members_per_project',
                    'exception_key' => 'members',
                    'message_subject' => 'members for this project',
                    'display_label' => 'Members',
                    'loaded_count_attribute' => 'active_members_count',
                    'count_loader_keys' => ['activeMembers as active_members_count'],
                    'scope' => 'project',
                    'requires_project' => true,
                ],
            ],
            'created meetings' => [
                PlanLimitType::CreatedMeetings,
                [
                    'value' => 'created_meetings',
                    'config_key' => 'max_created_meetings',
                    'exception_key' => 'meetings',
                    'message_subject' => 'created meetings',
                    'display_label' => 'Created meetings',
                    'loaded_count_attribute' => 'meetings_count',
                    'count_loader_keys' => ['meetings as meetings_count'],
                    'scope' => 'account',
                    'requires_project' => false,
                ],
            ],
            'api tokens' => [
                PlanLimitType::ApiTokens,
                [
                    'value' => 'api_tokens',
                    'config_key' => 'max_api_tokens',
                    'exception_key' => 'api_tokens',
                    'message_subject' => 'API tokens',
                    'display_label' => 'API tokens',
                    'loaded_count_attribute' => 'tokens_count',
                    'count_loader_keys' => ['tokens'],
                    'scope' => 'account',
                    'requires_project' => false,
                ],
            ],
        ];
    }

    /**
     * @param  array{value: string, config_key: string, exception_key: string, message_subject: string, display_label: string, loaded_count_attribute: string, count_loader_keys: array<int, string>, scope: string, requires_project: bool}  $expected
     */
    #[Test]
    #[DataProvider('metadataProvider')]
    public function it_exposes_consistent_metadata_for_each_limit_type(PlanLimitType $type, array $expected): void
    {
        $this->assertSame($expected['value'], $type->value);
        $this->assertSame($expected['config_key'], $type->configKey());
        $this->assertSame($expected['exception_key'], $type->exceptionKey());
        $this->assertSame($expected['message_subject'], $type->messageSubject());
        $this->assertSame($expected['display_label'], $type->displayLabel());
        $this->assertSame($expected['loaded_count_attribute'], $type->loadedCountAttribute());
        $this->assertSame($expected['count_loader_keys'], $this->normalizeCountLoaderKeys($type->countLoaders()));
        $this->assertSame($expected['scope'], $type->scope());
        $this->assertSame($expected['requires_project'], $type->requiresProject());
    }

    #[Test]
    public function it_groups_limit_types_by_scope(): void
    {
        $accountTypes = array_map(static fn (PlanLimitType $type): string => $type->value, PlanLimitType::accountTypes());
        $projectTypes = array_map(static fn (PlanLimitType $type): string => $type->value, PlanLimitType::projectTypes());

        $this->assertSame([
            'projects',
            'created_meetings',
            'api_tokens',
        ], $accountTypes);

        $this->assertSame([
            'tasks_per_project',
            'members_per_project',
        ], $projectTypes);
    }

    #[Test]
    public function it_aggregates_count_loaders_by_scope(): void
    {
        $this->assertSame([
            'projects',
            'meetings as meetings_count',
            'tokens',
        ], $this->normalizeCountLoaderKeys(PlanLimitType::accountCountLoaders()));

        $this->assertSame([
            'tasks as active_tasks_count',
            'activeMembers as active_members_count',
        ], $this->normalizeCountLoaderKeys(PlanLimitType::projectCountLoaders()));
    }

    /**
     * @param  array<int|string, mixed>  $loaders
     * @return array<int, string>
     */
    private function normalizeCountLoaderKeys(array $loaders): array
    {
        return array_values(array_map(
            static fn (int|string $key, mixed $loader): string => is_string($key) ? $key : $loader,
            array_keys($loaders),
            $loaders,
        ));
    }
}
