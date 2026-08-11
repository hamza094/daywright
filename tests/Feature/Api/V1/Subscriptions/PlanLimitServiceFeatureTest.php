<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Subscriptions;

use App\Enums\Subscription\PlanLimitType;
use App\Enums\Subscription\SubscriptionPlan;
use App\Enums\TaskSystemStatus;
use App\Exceptions\Subscription\PlanLimitExceededException;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Override;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Helpers\FixtureHelpers;
use Tests\Helpers\SubscriptionHelpers;
use Tests\TestCase;
use Tests\Traits\AuthenticatedProjectHelpers;

class PlanLimitServiceFeatureTest extends TestCase
{
    use AuthenticatedProjectHelpers, FixtureHelpers, RefreshDatabase, SubscriptionHelpers;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.paddle.monthly', 101);
        config()->set('services.paddle.yearly', 202);

        // FixtureHelpers seeds the task statuses required by the task factories.
        $this->createTaskStatuses();

        // AuthenticatedProjectHelpers provisions the signed-in user and default project.
        $this->setUpAuthenticatedUserWithProject(disableSubscriptionMiddleware: true);
    }

    //  Projects
    #[Test]
    public function free_user_is_blocked_from_creating_a_fourth_project(): void
    {
        $projectLimit = $this->freePlanLimit(PlanLimitType::Projects);

        Project::factory()->count($projectLimit - 1)->for($this->user)->create();

        $response = $this->createProject([
            'name' => 'New Project',
            'about' => 'New project description',
            'stage_id' => 1,
        ]);

        $this->assertPlanLimitExceeded(
            response: $response,
            reason: 'limit_reached',
            limitType: 'projects',
            currentUsage: $projectLimit,
            maxAllowed: $projectLimit,
            expectedMessage: 'You have reached the maximum number of projects on the Free plan.',
            expectedLimitScope: PlanLimitExceededException::SCOPE_ACCOUNT,
            expectedCanUpgrade: true,
        );

        $this->assertDatabaseMissing('projects', ['name' => 'New Project']);
    }

    #[Test]
    public function pro_user_can_create_more_than_three_projects(): void
    {
        // SubscriptionHelpers seeds a recurring Paddle subscription for the owner.
        $this->createProSubscription($this->user);

        $projectLimit = $this->freePlanLimit(PlanLimitType::Projects);
        Project::factory()->count($projectLimit)->for($this->user)->create();

        $response = $this->createProject([
            'name' => 'Fourth Project',
            'about' => 'Pro user project',
            'stage_id' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Fourth Project');
    }

    #[Test]
    public function free_user_above_the_project_limit_receives_an_over_limit_message(): void
    {
        $projectLimit = $this->freePlanLimit(PlanLimitType::Projects);

        Project::factory()->count($projectLimit + 1)->for($this->user)->create();
        $currentUsage = $this->user->projects()->count();

        $response = $this->createProject([
            'name' => 'Another Project',
            'about' => 'Blocked because current usage is already above the free cap',
            'stage_id' => 1,
        ]);

        $this->assertPlanLimitExceeded(
            response: $response,
            reason: 'limit_reached',
            limitType: 'projects',
            currentUsage: $currentUsage,
            maxAllowed: $projectLimit,
            expectedMessage: 'You are already above the maximum number of projects allowed on the Free plan. Reduce usage or upgrade your plan before creating more.',
            expectedLimitScope: PlanLimitExceededException::SCOPE_ACCOUNT,
            expectedCanUpgrade: true,
        );
    }

    //  Tasks
    #[Test]
    public function free_user_is_blocked_from_creating_an_eleventh_active_task(): void
    {
        $taskLimit = $this->freePlanLimit(PlanLimitType::TasksPerProject);

        $this->createActiveTasks($taskLimit);

        $response = $this->createTask(['title' => 'New Task']);

        $this->assertPlanLimitExceeded(
            response: $response,
            reason: 'limit_reached',
            limitType: 'tasks_per_project',
            currentUsage: $taskLimit,
            maxAllowed: $taskLimit,
            expectedMessage: "This project has reached its task limit ({$taskLimit}/{$taskLimit}). Ask the project owner to upgrade the plan or delete existing tasks.",
            expectedLimitScope: PlanLimitExceededException::SCOPE_PROJECT,
            expectedCanUpgrade: true,
        );
    }

    #[Test]
    public function member_is_blocked_when_the_project_owner_has_reached_the_active_task_limit_even_if_member_has_a_pro_plan(): void
    {
        $taskLimit = $this->freePlanLimit(PlanLimitType::TasksPerProject);

        $member = User::factory()->create();

        $this->project->members()->attach($member, ['active' => true]);
        $this->createProSubscription($member);
        $this->createActiveTasks($taskLimit);

        Sanctum::actingAs($member);

        $response = $this->createTask(['title' => 'Member Task']);

        $this->assertPlanLimitExceeded(
            response: $response,
            reason: 'limit_reached',
            limitType: 'tasks_per_project',
            currentUsage: $taskLimit,
            maxAllowed: $taskLimit,
            expectedMessage: "This project has reached its task limit ({$taskLimit}/{$taskLimit}). Ask the project owner to upgrade the plan or delete existing tasks.",
            expectedLimitScope: PlanLimitExceededException::SCOPE_PROJECT,
            expectedCanUpgrade: false,
        );

        $this->assertDatabaseMissing('tasks', [
            'project_id' => $this->project->id,
            'title' => 'Member Task',
        ]);
    }

    #[Test]
    public function member_can_create_a_task_when_the_project_owners_plan_allows_it(): void
    {
        $taskLimit = $this->freePlanLimit(PlanLimitType::TasksPerProject);

        $member = User::factory()->create();

        $this->project->members()->attach($member, ['active' => true]);
        $this->createProSubscription($this->user);
        $this->createActiveTasks($taskLimit);

        Sanctum::actingAs($member);

        $response = $this->createTask(['title' => 'Member Task']);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Member Task');

        $this->assertDatabaseHas('tasks', [
            'project_id' => $this->project->id,
            'user_id' => $member->id,
            'title' => 'Member Task',
        ]);
    }

    //  Members / Invitations
    #[Test]
    public function free_user_is_blocked_from_inviting_a_fourth_member(): void
    {
        $memberLimit = $this->freePlanLimit(PlanLimitType::MembersPerProject);

        $this->attachActiveMembers($memberLimit);

        /** @var User $invitee */
        $invitee = User::factory()->create();

        $response = $this->inviteMember($invitee->email);

        $this->assertPlanLimitExceeded(
            response: $response,
            reason: 'limit_reached',
            limitType: 'members',
            currentUsage: $memberLimit,
            maxAllowed: $memberLimit,
            expectedMessage: "This project has reached its member limit ({$memberLimit}/{$memberLimit}). Unable to join. Ask the project owner to upgrade the plan or remove inactive members.",
            expectedLimitScope: PlanLimitExceededException::SCOPE_PROJECT,
            expectedCanUpgrade: true,
        );
    }

    //  Meetings
    #[Test]
    public function free_user_is_blocked_from_creating_a_second_meeting(): void
    {
        $meetingLimit = $this->freePlanLimit(PlanLimitType::CreatedMeetings);

        Meeting::factory()->count($meetingLimit)->for($this->user)->for($this->project)->create();

        $this->mock(Zoom::class, function ($mock): void {
            $mock->shouldNotReceive('createMeeting');
        });

        $response = $this->createMeeting();

        $this->assertPlanLimitExceeded(
            response: $response,
            reason: 'limit_reached',
            limitType: 'meetings',
            currentUsage: $meetingLimit,
            maxAllowed: $meetingLimit,
            expectedMessage: 'You have reached the maximum number of created meetings on the Free plan.',
            expectedLimitScope: PlanLimitExceededException::SCOPE_ACCOUNT,
            expectedCanUpgrade: true,
        );
    }

    //  API Tokens
    #[Test]
    public function free_user_is_blocked_from_creating_a_second_api_token(): void
    {
        // Create a fresh user with session auth for token routes (avoiding Sanctum conflicts)
        $sessionUser = User::factory()->create();
        $this->actingAs($sessionUser);

        $apiTokenLimit = $this->freePlanLimit(PlanLimitType::ApiTokens);

        $this->createApiTokens($sessionUser, $apiTokenLimit);

        $response = $this->actingAs($sessionUser)
            ->withHeaders($this->idempotencyHeaders())
            ->postJson(route('api.v1.api-tokens.store'), [
                'name' => 'Blocked Token',
                'scopes' => ['account:read'],
            ]);

        $this->assertPlanLimitExceeded(
            response: $response,
            reason: 'limit_reached',
            limitType: 'api_tokens',
            currentUsage: $apiTokenLimit,
            maxAllowed: $apiTokenLimit,
            expectedMessage: 'You have reached the maximum number of API tokens on the Free plan.',
            expectedLimitScope: PlanLimitExceededException::SCOPE_ACCOUNT,
            expectedCanUpgrade: true,
        );
    }

    /**
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    private function assertPlanLimitExceeded(
        TestResponse $response,
        string $reason,
        string $limitType,
        int $currentUsage,
        int $maxAllowed,
        string $expectedMessage,
        string $expectedLimitScope,
        bool $expectedCanUpgrade,
    ): void {
        $response->assertStatus(403)
            ->assertJson([
                'message' => $expectedMessage,
                'code' => 'plan_limit_exceeded',
                'errors' => [],
                'meta' => [
                    'reason' => $reason,
                    'limit_type' => $limitType,
                    'limit_label' => $this->expectedLimitLabel($limitType),
                    'current_usage' => $currentUsage,
                    'max_allowed' => $maxAllowed,
                    'limit_scope' => $expectedLimitScope,
                    'can_upgrade' => $expectedCanUpgrade,
                    'upgrade_required' => true,
                ],
            ])
            ->assertJsonMissingPath('meta.limit_owner_id');
    }

    private function expectedLimitLabel(string $limitType): string
    {
        return match ($limitType) {
            'projects' => 'Projects',
            'tasks_per_project' => 'Tasks',
            'members' => 'Members',
            'meetings' => 'Created meetings',
            'api_tokens' => 'API tokens',
            default => $limitType,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function createProject(array $payload): TestResponse
    {
        return $this->postJson(route('api.v1.projects.store'), $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function createTask(array $payload): TestResponse
    {
        return $this->postJson(route('api.v1.tasks.store', $this->project), $payload);
    }

    /**
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function inviteMember(string $email): TestResponse
    {
        return $this->withHeaders($this->idempotencyHeaders())->postJson(route('api.v1.send.invitation', $this->project), ['email' => $email]);
    }

    /**
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function createMeeting(): TestResponse
    {
        return $this->withHeaders($this->idempotencyHeaders())->postJson(route('api.v1.meetings.store', $this->project), $this->validMeetingPayload());
    }

    /**
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function createApiToken(string $name): TestResponse
    {
        return $this->actingAs($this->user)
            ->withHeaders($this->idempotencyHeaders())
            ->postJson(route('api.v1.api-tokens.store'), [
                'name' => $name,
                'scopes' => ['account:read'],
            ]);
    }

    private function createActiveTasks(int $count): void
    {
        Task::factory()->count($count)->for($this->user, 'owner')->for($this->project)->create([
            'status_id' => TaskSystemStatus::Pending->value,
        ]);
    }

    private function attachActiveMembers(int $count): void
    {
        $this->project->members()->attach(
            User::factory()->count($count)->create(),
            ['active' => true]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validMeetingPayload(): array
    {
        return [
            'topic' => 'Test Meeting',
            'agenda' => 'Test Agenda',
            'duration' => 30,
            'start_time' => Carbon::now()->addDay()->toIso8601String(),
            'timezone' => 'UTC',
            'password' => 'abc1234',
            'join_before_host' => false,
        ];
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
