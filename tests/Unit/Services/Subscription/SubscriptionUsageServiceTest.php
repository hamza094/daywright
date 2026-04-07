<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Subscription;

use App\Enums\Subscription\PlanLimitType;
use App\Enums\Subscription\SubscriptionPlan;
use App\Enums\TaskStatus as TaskStatusEnum;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Api\V1\Subscription\SubscriptionUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\FixtureHelpers;
use Tests\TestCase;

class SubscriptionUsageServiceTest extends TestCase
{
    use FixtureHelpers, RefreshDatabase;

    private SubscriptionUsageService $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // FixtureHelpers seeds the task statuses required by the task factories.
        $this->createTaskStatuses();
        $this->service = resolve(SubscriptionUsageService::class);
    }

    #[Test]
    public function it_returns_account_usage_counts_and_limits(): void
    {
        $user = $this->makeUser();
        $this->createAccountUsageFixtures($user);

        $usage = $this->service->accountUsage($user);
        $this->assertAccountUsage($usage);

        $user->load('subscriptions', 'customer');
        $this->expectsDatabaseQueryCount(1);

        $usage = $this->service->accountUsage($user);
        $this->assertAccountUsage($usage);
    }

    #[Test]
    public function it_returns_project_usage_counts_and_limits_and_uses_single_query_when_loaded(): void
    {
        $user = $this->makeUser();
        $project = $this->makeProject($user);

        $this->createProjectUsageFixtures($user, $project);

        $user->load('subscriptions', 'customer');
        $this->expectsDatabaseQueryCount(1);

        $usage = $this->service->projectUsage($user, $project);
        $this->assertProjectUsage($usage);
    }

    private function createAccountUsageFixtures(User $user): void
    {
        $project = $this->makeProject($user);

        Project::factory()->for($user)->create();

        // FixtureHelpers creates one meeting and one token for the account-usage assertions.
        $this->createMeeting($user, $project);
        $this->createApiToken($user);
    }

    private function createProjectUsageFixtures(User $user, Project $project): void
    {
        Task::factory()->count(3)->for($user, 'owner')->for($project)->create([
            'status_id' => TaskStatusEnum::PENDING,
        ]);
        Task::factory()->for($user, 'owner')->for($project)->completed()->create();

        $project->members()->attach(User::factory()->count(2)->create(), ['active' => true]);
        $project->members()->attach(User::factory()->create(), ['active' => false]);
    }

    /**
     * @param  array<int, array{key: string, label: string, scope: string, limit: array{used: int|null, max: int|null}}>  $usage
     */
    private function assertAccountUsage(array $usage): void
    {
        $free = SubscriptionPlan::Free;

        $this->assertSame([
            [
                'key' => 'projects',
                'label' => 'Projects',
                'scope' => 'account',
                'limit' => ['used' => 2, 'max' => $free->maxFor(PlanLimitType::Projects)],
            ],
            [
                'key' => 'created_meetings',
                'label' => 'Created meetings',
                'scope' => 'account',
                'limit' => ['used' => 1, 'max' => $free->maxFor(PlanLimitType::CreatedMeetings)],
            ],
            [
                'key' => 'api_tokens',
                'label' => 'API tokens',
                'scope' => 'account',
                'limit' => ['used' => 1, 'max' => $free->maxFor(PlanLimitType::ApiTokens)],
            ],
        ], $usage);
    }

    /**
     * @param  array<int, array{key: string, label: string, scope: string, limit: array{used: int|null, max: int|null}}>  $usage
     */
    private function assertProjectUsage(array $usage): void
    {
        $free = SubscriptionPlan::Free;

        $this->assertSame([
            [
                'key' => 'active_tasks_per_project',
                'label' => 'Active tasks',
                'scope' => 'project',
                'limit' => ['used' => 3, 'max' => $free->maxFor(PlanLimitType::ActiveTasksPerProject)],
            ],
            [
                'key' => 'members_per_project',
                'label' => 'Members',
                'scope' => 'project',
                'limit' => ['used' => 2, 'max' => $free->maxFor(PlanLimitType::MembersPerProject)],
            ],
        ], $usage);
    }

    private function makeUser(): User
    {
        /** @var User $user */
        $user = User::factory()->create();

        return $user;
    }

    private function makeProject(User $user): Project
    {
        /** @var Project $project */
        $project = Project::factory()->for($user)->create();

        return $project;
    }
}
