<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Subscriptions;

use App\Enums\Subscription\PlanLimitType;
use App\Enums\Subscription\SubscriptionPlan;
use App\Enums\TaskStatus as TaskStatusEnum;
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

        $this->assertPlanLimitExceeded($response, 'limit_reached', 'projects', $projectLimit, $projectLimit);

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
            ->assertJsonPath('message', 'Project Created Successfully');
    }

    //  Tasks
    #[Test]
    public function free_user_is_blocked_from_creating_an_eleventh_active_task(): void
    {
        $taskLimit = $this->freePlanLimit(PlanLimitType::ActiveTasksPerProject);

        $this->createActiveTasks($taskLimit);

        $response = $this->createTask(['title' => 'New Task']);

        $this->assertPlanLimitExceeded($response, 'limit_reached', 'active_tasks_per_project', $taskLimit, $taskLimit);
    }

    #[Test]
    public function member_is_blocked_when_the_project_owner_has_reached_the_active_task_limit_even_if_member_has_a_pro_plan(): void
    {
        $taskLimit = $this->freePlanLimit(PlanLimitType::ActiveTasksPerProject);

        $member = User::factory()->create();

        $this->project->members()->attach($member, ['active' => true]);
        $this->createProSubscription($member);
        $this->createActiveTasks($taskLimit);

        Sanctum::actingAs($member);

        $response = $this->createTask(['title' => 'Member Task']);

        $this->assertPlanLimitExceeded($response, 'limit_reached', 'active_tasks_per_project', $taskLimit, $taskLimit);

        $this->assertDatabaseMissing('tasks', [
            'project_id' => $this->project->id,
            'title' => 'Member Task',
        ]);
    }

    #[Test]
    public function member_can_create_a_task_when_the_project_owners_plan_allows_it(): void
    {
        $taskLimit = $this->freePlanLimit(PlanLimitType::ActiveTasksPerProject);

        $member = User::factory()->create();

        $this->project->members()->attach($member, ['active' => true]);
        $this->createProSubscription($this->user);
        $this->createActiveTasks($taskLimit);

        Sanctum::actingAs($member);

        $response = $this->createTask(['title' => 'Member Task']);

        $response->assertCreated()
            ->assertJsonPath('message', 'Task added Successfully')
            ->assertJsonPath('task.title', 'Member Task');

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

        $this->assertPlanLimitExceeded($response, 'limit_reached', 'members', $memberLimit, $memberLimit);
    }

    //  Meetings
    #[Test]
    public function free_user_is_blocked_from_creating_a_second_meeting(): void
    {
        $meetingLimit = $this->freePlanLimit(PlanLimitType::CreatedMeetings);

        Meeting::factory()->count($meetingLimit)->for($this->user)->for($this->project)->create();

        $this->mock(Zoom::class);

        $response = $this->createMeeting();

        $this->assertPlanLimitExceeded($response, 'limit_reached', 'meetings', $meetingLimit, $meetingLimit);
    }

    //  API Tokens
    #[Test]
    public function free_user_is_blocked_from_creating_a_second_api_token(): void
    {
        $apiTokenLimit = $this->freePlanLimit(PlanLimitType::ApiTokens);

        $this->createApiTokens($this->user, $apiTokenLimit);

        $response = $this->createApiToken('Blocked Token');

        $this->assertPlanLimitExceeded($response, 'limit_reached', 'api_tokens', $apiTokenLimit, $apiTokenLimit);
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
    ): void {
        $response->assertStatus(403)
            ->assertJson([
                'error_type' => 'plan_limit_exceeded',
                'reason' => $reason,
                'limit_type' => $limitType,
                'current_usage' => $currentUsage,
                'max_allowed' => $maxAllowed,
                'upgrade_required' => true,
            ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function createProject(array $payload): TestResponse
    {
        return $this->postJson(route('projects.store'), $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function createTask(array $payload): TestResponse
    {
        return $this->postJson(route('tasks.store', $this->project), $payload);
    }

    /**
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function inviteMember(string $email): TestResponse
    {
        return $this->postJson(route('send.invitation', $this->project), ['email' => $email]);
    }

    /**
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function createMeeting(): TestResponse
    {
        return $this->postJson(route('meetings.store', $this->project), $this->validMeetingPayload());
    }

    /**
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function createApiToken(string $name): TestResponse
    {
        return $this->postJson(route('api-tokens.store'), ['name' => $name]);
    }

    private function createActiveTasks(int $count): void
    {
        Task::factory()->count($count)->for($this->user, 'owner')->for($this->project)->create([
            'status_id' => TaskStatusEnum::PENDING,
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
            'start_time' => Carbon::now()->addDay()->toDateTimeString(),
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
