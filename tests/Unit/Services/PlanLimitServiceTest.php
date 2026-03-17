<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\PlanLimitType;
use App\Enums\SubscriptionPlan;
use App\Enums\TaskStatus as TaskStatusEnum;
use App\Exceptions\Subscription\PlanLimitExceededException;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Api\V1\Subscription\PlanLimitService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\FixtureHelpers;
use Tests\Traits\SubscriptionHelpers;

class PlanLimitServiceTest extends TestCase
{
    use FixtureHelpers, RefreshDatabase, SubscriptionHelpers;

    private PlanLimitService $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.paddle.monthly', 101);
        config()->set('services.paddle.yearly', 202);

        $this->createTaskStatuses();
        $this->service = app(PlanLimitService::class);
    }

    /**
     * Data provider for project limit reason scenarios.
     *
     * Each tuple provides: [setupMethod, expectedReason, expectedPlan]
     *
     * @return array<int, array{0: string, 1: string, 2: SubscriptionPlan}>
     */
    public static function projectLimitReasonProvider(): array
    {
        return [
            // free user
            ['setUpFreeUserAtProjectLimit', PlanLimitExceededException::REASON_LIMIT_REACHED, SubscriptionPlan::Free],

            // expired trial user
            ['setUpExpiredTrialUserAtProjectLimit', PlanLimitExceededException::REASON_TRIAL_EXPIRED, SubscriptionPlan::Free],

            // post grace user
            ['setUpPostGraceUserAtProjectLimit', PlanLimitExceededException::REASON_LIMIT_REACHED, SubscriptionPlan::Free],
        ];
    }

    /**
     * Data provider for single-user limits (meeting, api token).
     *
     * Each tuple provides: [limitType, consumeMethod, expectedLimitType]
     *
     * @return array<int, array{0: PlanLimitType, 1: string, 2: string}>
     */
    public static function userLimitProvider(): array
    {
        return [
            [PlanLimitType::CreatedMeetings, 'createMeeting', 'meetings'],
            [PlanLimitType::ApiTokens, 'createApiToken', 'api_tokens'],
        ];
    }

    #[Test]
    #[DataProvider('projectLimitReasonProvider')]
    public function it_exposes_project_limit_metadata_for_each_free_plan_state(
        string $setupMethod,
        string $expectedReason,
        SubscriptionPlan $expectedPlan,
    ): void {
        // SubscriptionHelpers: prepares the user/project state for this scenario
        $user = $this->{$setupMethod}();

        // PlanLimitService: determine plan and flags
        $this->assertSame($expectedPlan, $this->service->plan($user));

        // PlanLimitService->assertWithinLimit throws PlanLimitExceededException
        $this->assertPlanException(
            callback: fn (): null => $this->service->assertWithinLimit(PlanLimitType::Projects, $user),
            expectedLimitType: 'projects',
            expectedReason: $expectedReason,
            expectedUsage: 3,
            expectedMax: 3,
            expectedMessage: 'You have reached the maximum number of projects on the Free plan.',
        );
    }

    #[Test]
    public function it_counts_only_active_tasks_when_enforcing_the_task_limit(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        // Fixture (TaskFactory): create active and completed tasks
        Task::factory()->count(9)->for($user, 'owner')->for($project)->create([
            'status_id' => TaskStatusEnum::PENDING,
        ]);

        Task::factory()->for($user, 'owner')->for($project)->completed()->create();

        // Fixture: create then soft-delete an archived task (should not count)
        $archivedTask = Task::factory()->for($user, 'owner')->for($project)->create([
            'status_id' => TaskStatusEnum::IN_PROGRESS,
        ]);
        $archivedTask->delete();

        // PlanLimitService: currently within task limit
        $this->service->assertWithinLimit(PlanLimitType::ActiveTasksPerProject, $user, $project);

        // Fixture: add one more active task to reach the limit
        Task::factory()->for($user, 'owner')->for($project)->remaining()->create();

        // PlanLimitService: now exceeds the task limit
        $this->assertPlanException(
            callback: fn (): null => $this->service->assertWithinLimit(PlanLimitType::ActiveTasksPerProject, $user, $project),
            expectedLimitType: 'active_tasks_per_project',
            expectedReason: PlanLimitExceededException::REASON_LIMIT_REACHED,
            expectedUsage: 10,
            expectedMax: 10,
        );
    }

    #[Test]
    public function it_counts_only_active_members_when_enforcing_the_member_limit(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        // Fixture: attach active and inactive members
        $project->members()->attach(User::factory()->count(2)->create(), ['active' => true]);
        $project->members()->attach(User::factory()->create(), ['active' => false]);

        // PlanLimitService: still under member limit
        $this->service->assertWithinLimit(PlanLimitType::MembersPerProject, $user, $project);

        // Fixture: add another active member to reach the limit
        $project->members()->attach(User::factory()->create(), ['active' => true]);

        // PlanLimitService: now exceeds the member limit
        $this->assertPlanException(
            callback: fn (): null => $this->service->assertWithinLimit(PlanLimitType::MembersPerProject, $user, $project),
            expectedLimitType: 'members',
            expectedReason: PlanLimitExceededException::REASON_LIMIT_REACHED,
            expectedUsage: 3,
            expectedMax: 3,
        );
    }

    #[Test]
    #[DataProvider('userLimitProvider')]
    public function it_enforces_single_user_free_limits(
        PlanLimitType $limitType,
        string $consumeMethod,
        string $expectedLimitType,
    ): void {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        // PlanLimitService: initially allowed to create the resource
        $this->service->assertWithinLimit($limitType, $user);

        // FixtureHelpers: consume the resource (createMeeting/createApiToken)
        $this->{$consumeMethod}($user, $project);

        // PlanLimitService: creation now disallowed
        // PlanLimitService->assertWithinLimit throws PlanLimitExceededException
        $this->assertPlanException(
            callback: fn (): null => $this->service->assertWithinLimit($limitType, $user),
            expectedLimitType: $expectedLimitType,
            expectedReason: PlanLimitExceededException::REASON_LIMIT_REACHED,
            expectedUsage: 1,
            expectedMax: 1,
        );
    }

    #[Test]
    public function pro_users_have_unlimited_access_to_configured_limits(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        // SubscriptionHelpers: create an active Pro subscription for the user
        $this->createProSubscription($user);
        Project::factory()->count(4)->for($user)->create();
        Task::factory()->count(12)->for($user, 'owner')->for($project)->create([
            'status_id' => TaskStatusEnum::PENDING,
        ]);

        $project->members()->attach(User::factory()->count(4)->create(), ['active' => true]);
        Meeting::factory()->count(2)->for($user)->for($project)->create();
        $user->createToken('token-one');
        $user->createToken('token-two');

        $this->assertSame(SubscriptionPlan::Pro, $this->service->plan($user));
        $this->service->assertWithinLimit(PlanLimitType::Projects, $user);
        $this->service->assertWithinLimit(PlanLimitType::ActiveTasksPerProject, $user, $project);
        $this->service->assertWithinLimit(PlanLimitType::MembersPerProject, $user, $project);
        $this->service->assertWithinLimit(PlanLimitType::CreatedMeetings, $user);
        $this->service->assertWithinLimit(PlanLimitType::ApiTokens, $user);
    }

    #[Test]
    public function trial_users_receive_pro_access(): void
    {
        $user = User::factory()->create();

        // SubscriptionHelpers: create a customer with an active trial
        $this->createTrialCustomer($user, Carbon::now()->addDays(7));
        Project::factory()->count(3)->for($user)->create();

        $this->assertSame(SubscriptionPlan::Pro, $this->service->plan($user));
        $this->assertTrue($user->isOnTrial());
        $this->service->assertWithinLimit(PlanLimitType::Projects, $user);
    }

    #[Test]
    public function grace_period_users_keep_pro_access(): void
    {
        $user = User::factory()->create();

        // SubscriptionHelpers: create a subscription currently in grace period
        $this->createGracePeriodSubscription($user);
        Project::factory()->count(3)->for($user)->create();

        $this->assertSame(SubscriptionPlan::Pro, $this->service->plan($user));
        $this->assertTrue($user->isInGracePeriod());
        $this->service->assertWithinLimit(PlanLimitType::Projects, $user);
    }

    #[Test]
    public function it_returns_usage_counts_and_limits(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        // Fixture: additional project and tasks for usage counts
        Project::factory()->for($user)->create();
        Task::factory()->count(3)->for($user, 'owner')->for($project)->create([
            'status_id' => TaskStatusEnum::PENDING,
        ]);
        Task::factory()->for($user, 'owner')->for($project)->completed()->create();

        $project->members()->attach(User::factory()->count(2)->create(), ['active' => true]);
        $project->members()->attach(User::factory()->create(), ['active' => false]);

        // FixtureHelpers: create a meeting and API token for usage
        Meeting::factory()->for($user)->for($project)->create();
        $user->createToken('usage-token');

        // PlanLimitService: retrieve usage counts and limits
        $usage = $this->service->usage($user, $project);

        $this->assertSame(['used' => 2, 'max' => 3], $usage['projects']);
        $this->assertSame(['used' => 3, 'max' => 10], $usage['active_tasks_per_project']);
        $this->assertSame(['used' => 2, 'max' => 3], $usage['members_per_project']);
        $this->assertSame(['used' => 1, 'max' => 1], $usage['created_meetings']);
        $this->assertSame(['used' => 1, 'max' => 1], $usage['api_tokens']);
    }

    /**
     * @param  callable(): null  $callback
     */
    private function assertPlanException(
        callable $callback,
        string $expectedLimitType,
        string $expectedReason,
        int $expectedUsage,
        int $expectedMax,
        ?string $expectedMessage = null,
    ): void {
        // Helper that asserts a PlanLimitExceededException thrown by PlanLimitService
        try {
            $callback();
            $this->fail('Expected PlanLimitExceededException to be thrown.');
        } catch (PlanLimitExceededException $exception) {
            $this->assertSame($expectedLimitType, $exception->limitType());
            $this->assertSame($expectedReason, $exception->reason());
            $this->assertSame($expectedUsage, $exception->currentUsage());
            $this->assertSame($expectedMax, $exception->maxAllowed());

            if ($expectedMessage !== null) {
                $this->assertSame($expectedMessage, $exception->getMessage());
            }
        }
    }
}
