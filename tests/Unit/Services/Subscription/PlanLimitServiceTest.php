<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Subscription;

use App\Enums\Subscription\PlanLimitType;
use App\Enums\Subscription\SubscriptionPlan;
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
use RuntimeException;
use Tests\Helpers\FixtureHelpers;
use Tests\Helpers\SubscriptionHelpers;
use Tests\TestCase;

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

        // FixtureHelpers seeds the task statuses required by the task factories.
        $this->createTaskStatuses();
        $this->service = resolve(PlanLimitService::class);
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public static function projectLimitReasonProvider(): array
    {
        // SubscriptionHelpers builds these free, expired-trial, and post-grace user states.
        return [
            ['setUpFreeUserAtProjectLimit', PlanLimitExceededException::REASON_LIMIT_REACHED],
            ['setUpExpiredTrialUserAtProjectLimit', PlanLimitExceededException::REASON_TRIAL_EXPIRED],
            ['setUpPostGraceUserAtProjectLimit', PlanLimitExceededException::REASON_LIMIT_REACHED],
        ];
    }

    /**
     * @return array<int, array{0: PlanLimitType, 1: string, 2: bool}>
     */
    public static function userLimitProvider(): array
    {
        // FixtureHelpers consumes one resource for the selected free-plan limit.
        return [
            [PlanLimitType::CreatedMeetings, 'createMeeting', true],
            [PlanLimitType::ApiTokens, 'createApiToken', false],
        ];
    }

    // ---------------------------------------------------------------------
    // Tests
    // Each test includes a short comment indicating whether it uses
    // private helper methods (below) or external test helpers.
    // ---------------------------------------------------------------------

    #[Test]
    #[DataProvider('projectLimitReasonProvider')]
    public function it_exposes_project_limit_metadata_for_each_free_plan_state(
        string $setupMethod,
        string $expectedReason,
    ): void {
        // Uses: SubscriptionHelpers (external) and private helpers: freePlanLimit(), assertPlanException()
        $projectLimit = $this->freePlanLimit(PlanLimitType::Projects);

        // SubscriptionHelpers resolves the requested account state from the provider.
        $user = $this->{$setupMethod}();

        $this->assertSame(SubscriptionPlan::Free, $this->service->plan($user));

        $this->assertPlanException(
            callback: fn (): null => $this->service->assertWithinLimit(PlanLimitType::Projects, $user),
            expectedLimitType: 'projects',
            expectedReason: $expectedReason,
            expectedUsage: $projectLimit,
            expectedMax: $projectLimit,
            expectedMessage: 'You have reached the maximum number of projects on the Free plan.',
            expectedLimitScope: PlanLimitExceededException::SCOPE_ACCOUNT,
        );
    }

    #[Test]
    public function it_counts_only_active_tasks_when_enforcing_the_task_limit(): void
    {
        // Uses: private seeder helper seedProjectJustBelowActiveTaskLimit() and assertPlanException()
        $user = $this->makeUser();
        $project = $this->makeProject($user);
        $taskLimit = $this->freePlanLimit(PlanLimitType::ActiveTasksPerProject);

        $this->seedProjectJustBelowActiveTaskLimit($user, $project, $taskLimit);

        $this->service->assertWithinLimit(PlanLimitType::ActiveTasksPerProject, $user, $project);

        Task::factory()->for($user, 'owner')->for($project)->remaining()->create();

        $this->assertPlanException(
            callback: fn (): null => $this->service->assertWithinLimit(PlanLimitType::ActiveTasksPerProject, $user, $project),
            expectedLimitType: 'active_tasks_per_project',
            expectedReason: PlanLimitExceededException::REASON_LIMIT_REACHED,
            expectedUsage: $taskLimit,
            expectedMax: $taskLimit,
            expectedMessage: 'This project has reached the maximum number of active tasks allowed on its current plan.',
            expectedLimitScope: PlanLimitExceededException::SCOPE_PROJECT,
        );
    }

    #[Test]
    public function it_returns_an_over_limit_message_when_project_usage_is_already_above_the_current_plan_cap(): void
    {
        $user = $this->makeUser();
        $project = $this->makeProject($user);
        $taskLimit = $this->freePlanLimit(PlanLimitType::ActiveTasksPerProject);

        Task::factory()->count($taskLimit + 1)->for($user, 'owner')->for($project)->create([
            'status_id' => TaskStatusEnum::PENDING,
        ]);

        $this->assertPlanException(
            callback: fn (): null => $this->service->assertWithinLimit(PlanLimitType::ActiveTasksPerProject, $user, $project),
            expectedLimitType: 'active_tasks_per_project',
            expectedReason: PlanLimitExceededException::REASON_LIMIT_REACHED,
            expectedUsage: $taskLimit + 1,
            expectedMax: $taskLimit,
            expectedMessage: 'This project is already above the maximum number of active tasks allowed on its current plan. Reduce usage before creating more.',
            expectedLimitScope: PlanLimitExceededException::SCOPE_PROJECT,
        );
    }

    #[Test]
    public function it_counts_only_active_members_when_enforcing_the_member_limit(): void
    {
        // Uses: private seeder helper seedProjectJustBelowMemberLimit() and addActiveProjectMember()
        $user = $this->makeUser();
        $project = $this->makeProject($user);
        $memberLimit = $this->freePlanLimit(PlanLimitType::MembersPerProject);

        $this->seedProjectJustBelowMemberLimit($project, $memberLimit);

        $this->service->assertWithinLimit(PlanLimitType::MembersPerProject, $user, $project);

        $this->addActiveProjectMember($project);

        $this->assertPlanException(
            callback: fn (): null => $this->service->assertWithinLimit(PlanLimitType::MembersPerProject, $user, $project),
            expectedLimitType: 'members',
            expectedReason: PlanLimitExceededException::REASON_LIMIT_REACHED,
            expectedUsage: $memberLimit,
            expectedMax: $memberLimit,
            expectedMessage: 'This project has reached the maximum number of members allowed on its current plan.',
            expectedLimitScope: PlanLimitExceededException::SCOPE_PROJECT,
        );
    }

    #[Test]
    #[DataProvider('userLimitProvider')]
    public function it_enforces_single_user_free_limits(
        PlanLimitType $limitType,
        string $consumeMethod,
        bool $requiresProject,
    ): void {
        // Uses: consumeSingleUserFreeLimitToBoundary() (private) and FixtureHelpers to consume resources
        $user = $this->makeUser();
        $project = $requiresProject ? $this->makeProject($user) : null;
        $maxAllowed = $this->consumeSingleUserFreeLimitToBoundary($limitType, $consumeMethod, $user, $project);

        $this->{$consumeMethod}($user, $project);

        $this->assertPlanException(
            callback: fn (): null => $this->service->assertWithinLimit($limitType, $user, $project),
            expectedLimitType: $limitType->exceptionKey(),
            expectedReason: PlanLimitExceededException::REASON_LIMIT_REACHED,
            expectedUsage: $maxAllowed,
            expectedMax: $maxAllowed,
        );
    }

    #[Test]
    public function pro_users_have_unlimited_access_to_configured_limits(): void
    {
        // Uses: SubscriptionHelpers::createProSubscription() and private seeder/assert helpers
        $user = $this->makeUser();
        $project = $this->makeProject($user);

        $this->createProSubscription($user);
        $this->seedUsageBeyondFreeLimits($user, $project);

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
        // Uses: SubscriptionHelpers (createTrialCustomer, createGracePeriodSubscription)
        //       and private helpers: seedProjectsBeyondFreeLimit(), assertProjectLimitRemainsAvailableOnProPlan()
        $trialUser = $this->makeUser();

        $this->createTrialCustomer($trialUser, Carbon::now()->addDays(7));
        $this->seedProjectsBeyondFreeLimit($trialUser);

        $this->assertProjectLimitRemainsAvailableOnProPlan($trialUser);

        $graceUser = $this->makeUser();

        $this->createGracePeriodSubscription($graceUser);
        $this->seedProjectsBeyondFreeLimit($graceUser);

        $this->assertProjectLimitRemainsAvailableOnProPlan($graceUser);
    }

    #[Test]
    public function it_executes_account_limited_callbacks_after_locking_and_rechecking_usage(): void
    {
        // Uses: local factory helper makeUser() and PlanLimitService::executeWithinAccountLimit()
        $user = $this->makeUser();

        $createdProject = $this->service->executeWithinAccountLimit(
            PlanLimitType::Projects,
            $user,
            fn (User $lockedUser): Project => $lockedUser->projects()->create([
                'name' => 'Locked Project',
                'about' => 'Created within account limit guard',
                'stage_id' => 1,
            ])
        );

        $this->assertDatabaseHas('projects', [
            'id' => $createdProject->id,
            'user_id' => $user->id,
            'name' => 'Locked Project',
        ]);
    }

    #[Test]
    public function it_executes_project_limited_callbacks_after_locking_and_rechecking_usage(): void
    {
        // Uses: local factory helpers (makeUser/makeProject) and PlanLimitService::executeWithinProjectLimit()
        $user = $this->makeUser();
        $project = $this->makeProject($user);

        $task = $this->service->executeWithinProjectLimit(
            PlanLimitType::ActiveTasksPerProject,
            $project,
            fn (Project $lockedProject): Task => $lockedProject->tasks()->create([
                'title' => 'Locked Task',
                'user_id' => $user->id,
                'status_id' => TaskStatusEnum::PENDING,
            ])
        );

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Locked Task',
        ]);
    }

    #[Test]
    public function it_uses_preloaded_counts_for_every_account_limit_type(): void
    {
        $user = $this->makeUser();

        $this->makeProject($user);

        Project::factory()->for($user)->create();

        $user->load('subscriptions', 'customer');
        $user->loadCount(PlanLimitType::accountCountLoaders());

        $this->expectsDatabaseQueryCount(0);

        foreach (PlanLimitType::accountTypes() as $type) {
            $this->service->assertWithinLimit($type, $user);
        }
    }

    #[Test]
    public function it_uses_preloaded_counts_for_every_project_limit_type(): void
    {
        $user = $this->makeUser();
        $project = $this->makeProject($user);

        $this->seedProjectJustBelowActiveTaskLimit($user, $project, $this->freePlanLimit(PlanLimitType::ActiveTasksPerProject));
        $this->seedProjectJustBelowMemberLimit($project, $this->freePlanLimit(PlanLimitType::MembersPerProject));

        $user->load('subscriptions', 'customer');
        $project->loadCount(PlanLimitType::projectCountLoaders());

        $this->expectsDatabaseQueryCount(0);

        foreach (PlanLimitType::projectTypes() as $type) {
            $this->service->assertWithinLimit($type, $user, $project);
        }
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
        ?string $expectedLimitScope = null,
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

            if ($expectedLimitScope !== null) {
                $this->assertSame($expectedLimitScope, $exception->limitScope());
            }
        }
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

    private function seedProjectJustBelowMemberLimit(Project $project, int $memberLimit): void
    {
        $project->members()->attach(User::factory()->count($memberLimit - 1)->create(), ['active' => true]);
        $project->members()->attach(User::factory()->create(), ['active' => false]);
    }

    private function addActiveProjectMember(Project $project): void
    {
        $project->members()->attach(User::factory()->create(), ['active' => true]);
    }

    private function consumeSingleUserFreeLimitToBoundary(
        PlanLimitType $limitType,
        string $consumeMethod,
        User $user,
        ?Project $project,
    ): int {
        $maxAllowed = $this->freePlanLimit($limitType);

        $this->service->assertWithinLimit($limitType, $user, $project);

        for ($usage = 1; $usage < $maxAllowed; $usage++) {
            $this->{$consumeMethod}($user, $project);
            $this->service->assertWithinLimit($limitType, $user, $project);
        }

        return $maxAllowed;
    }

    private function seedUsageBeyondFreeLimits(User $user, Project $project): void
    {
        Project::factory()->count($this->freePlanLimit(PlanLimitType::Projects) + 1)->for($user)->create();
        Task::factory()->count($this->freePlanLimit(PlanLimitType::ActiveTasksPerProject) + 1)->for($user, 'owner')->for($project)->create([
            'status_id' => TaskStatusEnum::PENDING,
        ]);
        $project->members()->attach(User::factory()->count($this->freePlanLimit(PlanLimitType::MembersPerProject) + 1)->create(), ['active' => true]);
        Meeting::factory()->count($this->freePlanLimit(PlanLimitType::CreatedMeetings) + 1)->for($user)->for($project)->create();
        $this->createApiTokens($user, $this->freePlanLimit(PlanLimitType::ApiTokens) + 1);
    }

    private function seedProjectsBeyondFreeLimit(User $user): void
    {
        Project::factory()->count($this->freePlanLimit(PlanLimitType::Projects) + 1)->for($user)->create();
    }

    private function assertProjectLimitRemainsAvailableOnProPlan(User $user): void
    {
        $this->assertSame(SubscriptionPlan::Pro, $this->service->plan($user));
        $this->service->assertWithinLimit(PlanLimitType::Projects, $user);
    }

    private function seedProjectJustBelowActiveTaskLimit(User $user, Project $project, int $taskLimit): void
    {
        // Create (taskLimit - 1) active/pending tasks
        Task::factory()->count($taskLimit - 1)->for($user, 'owner')->for($project)->create([
            'status_id' => TaskStatusEnum::PENDING,
        ]);

        // Create one completed task (should not be counted)
        Task::factory()->for($user, 'owner')->for($project)->completed()->create();

        // Create one task that will be archived (deleted) so it isn't counted
        $archivedTask = Task::factory()->for($user, 'owner')->for($project)->remaining()->create();
        $archivedTask->delete();
    }

    private function freePlanLimit(PlanLimitType $type): int
    {
        $limit = SubscriptionPlan::Free->maxFor($type);

        if ($limit === null) {
            throw new RuntimeException("Expected a configured free-plan limit for [{$type->value}].");
        }

        return $limit;
    }
}
