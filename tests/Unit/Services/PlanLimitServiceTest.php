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
     * @return array<int, array{0: string, 1: string}>
     */
    public static function projectLimitReasonProvider(): array
    {
        return [
            ['setUpFreeUserAtProjectLimit', PlanLimitExceededException::REASON_LIMIT_REACHED],
            ['setUpExpiredTrialUserAtProjectLimit', PlanLimitExceededException::REASON_TRIAL_EXPIRED],
            ['setUpPostGraceUserAtProjectLimit', PlanLimitExceededException::REASON_LIMIT_REACHED],
        ];
    }

    /**
     * @return array<int, array{0: PlanLimitType, 1: string}>
     */
    public static function userLimitProvider(): array
    {
        return [
            [PlanLimitType::CreatedMeetings, 'createMeeting'],
            [PlanLimitType::ApiTokens, 'createApiToken'],
        ];
    }

    #[Test]
    #[DataProvider('projectLimitReasonProvider')]
    public function it_exposes_project_limit_metadata_for_each_free_plan_state(
        string $setupMethod,
        string $expectedReason,
    ): void {
        $user = $this->{$setupMethod}();

        $this->assertSame(SubscriptionPlan::Free, $this->service->plan($user));

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

        Task::factory()->count(9)->for($user, 'owner')->for($project)->create([
            'status_id' => TaskStatusEnum::PENDING,
        ]);

        Task::factory()->for($user, 'owner')->for($project)->completed()->create();

        $archivedTask = Task::factory()->for($user, 'owner')->for($project)->create([
            'status_id' => TaskStatusEnum::IN_PROGRESS,
        ]);
        $archivedTask->delete();

        $this->service->assertWithinLimit(PlanLimitType::ActiveTasksPerProject, $user, $project);

        Task::factory()->for($user, 'owner')->for($project)->remaining()->create();

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

        $project->members()->attach(User::factory()->count(2)->create(), ['active' => true]);
        $project->members()->attach(User::factory()->create(), ['active' => false]);

        $this->service->assertWithinLimit(PlanLimitType::MembersPerProject, $user, $project);

        $project->members()->attach(User::factory()->create(), ['active' => true]);

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
    ): void {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->service->assertWithinLimit($limitType, $user);

        $this->{$consumeMethod}($user, $project);

        $this->assertPlanException(
            callback: fn (): null => $this->service->assertWithinLimit($limitType, $user),
            expectedLimitType: $limitType->exceptionKey(),
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
    public function trial_and_grace_period_users_receive_pro_access(): void
    {
        $trialUser = User::factory()->create();
        $this->createTrialCustomer($trialUser, Carbon::now()->addDays(7));
        Project::factory()->count(3)->for($trialUser)->create();

        $this->assertSame(SubscriptionPlan::Pro, $this->service->plan($trialUser));
        $this->service->assertWithinLimit(PlanLimitType::Projects, $trialUser);

        $graceUser = User::factory()->create();
        $this->createGracePeriodSubscription($graceUser);
        Project::factory()->count(3)->for($graceUser)->create();

        $this->assertSame(SubscriptionPlan::Pro, $this->service->plan($graceUser));
        $this->service->assertWithinLimit(PlanLimitType::Projects, $graceUser);
    }

    #[Test]
    public function it_returns_account_usage_counts_and_limits(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Project::factory()->for($user)->create();
        Task::factory()->count(3)->for($user, 'owner')->for($project)->create([
            'status_id' => TaskStatusEnum::PENDING,
        ]);
        Task::factory()->for($user, 'owner')->for($project)->completed()->create();

        $project->members()->attach(User::factory()->count(2)->create(), ['active' => true]);
        $project->members()->attach(User::factory()->create(), ['active' => false]);

        Meeting::factory()->for($user)->for($project)->create();
        $user->createToken('usage-token');

        $usage = $this->service->accountUsage($user);

        $this->assertSame(['used' => 2, 'max' => 3], $usage['projects']);
        $this->assertSame(['used' => 1, 'max' => 1], $usage['created_meetings']);
        $this->assertSame(['used' => 1, 'max' => 1], $usage['api_tokens']);
    }

    #[Test]
    public function it_returns_project_usage_counts_and_limits(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Task::factory()->count(3)->for($user, 'owner')->for($project)->create([
            'status_id' => TaskStatusEnum::PENDING,
        ]);
        Task::factory()->for($user, 'owner')->for($project)->completed()->create();

        $project->members()->attach(User::factory()->count(2)->create(), ['active' => true]);
        $project->members()->attach(User::factory()->create(), ['active' => false]);

        $usage = $this->service->projectUsage($user, $project);

        $this->assertSame(['used' => 3, 'max' => 10], $usage['active_tasks_per_project']);
        $this->assertSame(['used' => 2, 'max' => 3], $usage['members_per_project']);
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
