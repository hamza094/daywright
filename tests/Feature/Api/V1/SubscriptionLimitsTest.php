<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\DataTransferObjects\Zoom\Meeting as ZoomMeetingDTO;
use App\Enums\TaskStatus as TaskStatusEnum;
use App\Http\Middleware\CheckSubscription;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\FixtureHelpers;
use Tests\Traits\SubscriptionHelpers;

class SubscriptionLimitsTest extends TestCase
{
    use FixtureHelpers, RefreshDatabase, SubscriptionHelpers;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.paddle.monthly', 101);
        config()->set('services.paddle.yearly', 202);

        $this->createTaskStatuses();

        $this->user = User::factory()->create();

        Sanctum::actingAs($this->user);

        $this->project = Project::factory()->for($this->user)->create();

        $this->withoutMiddleware(CheckSubscription::class);
    }

    //  Projects
    #[Test]
    public function free_user_is_blocked_from_creating_a_fourth_project(): void
    {
        Project::factory()->count(2)->for($this->user)->create();

        $response = $this->createProject([
            'name' => 'Blocked Project',
            'about' => 'Should not be created',
            'stage_id' => 1,
        ]);

        $this->assertPlanLimitExceeded($response, 'limit_reached', 'projects', 3, 3);

        $this->assertDatabaseMissing('projects', ['name' => 'Blocked Project']);
    }

    #[Test]
    public function pro_user_can_create_more_than_three_projects(): void
    {
        $this->createProSubscription($this->user);

        Project::factory()->count(3)->for($this->user)->create();

        $response = $this->createProject([
            'name' => 'Fourth Project',
            'about' => 'Pro user project',
            'stage_id' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Project Created Successfully');
    }

    #[Test]
    public function trial_user_can_create_beyond_free_project_limit(): void
    {
        $this->createTrialCustomer($this->user, Carbon::now()->addDays(5));

        Project::factory()->count(3)->for($this->user)->create();

        $response = $this->createProject([
            'name' => 'Fourth Project During Trial',
            'about' => 'Trial user project',
            'stage_id' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Project Created Successfully');
    }

    #[Test]
    public function expired_trial_user_is_blocked_from_creating_a_fourth_project(): void
    {
        $this->createTrialCustomer($this->user, Carbon::now()->subDay());

        Project::factory()->count(2)->for($this->user)->create();

        $response = $this->createProject([
            'name' => 'Blocked Trial Project',
            'about' => 'Expired trial should not allow this',
            'stage_id' => 1,
        ]);

        $this->assertPlanLimitExceeded($response, 'trial_expired', 'projects', 3, 3);
    }

    //  Tasks
    #[Test]
    public function free_user_is_blocked_from_creating_an_eleventh_active_task(): void
    {
        $this->createActiveTasks(10);

        $response = $this->createTask(['title' => 'Blocked Task']);

        $this->assertPlanLimitExceeded($response, 'limit_reached', 'active_tasks_per_project', 10, 10);
    }

    #[Test]
    public function pro_user_can_create_more_than_ten_active_tasks(): void
    {
        $this->createProSubscription($this->user);

        $this->createActiveTasks(10);

        $response = $this->createTask(['title' => 'Eleventh Task']);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Task added Successfully');
    }

    //  Members / Invitations
    #[Test]
    public function free_user_is_blocked_from_inviting_a_fourth_member(): void
    {
        $this->attachActiveMembers(3);

        $invitee = User::factory()->create();

        $response = $this->inviteMember($invitee->email);

        $this->assertPlanLimitExceeded($response, 'limit_reached', 'members', 3, 3);
    }

    #[Test]
    public function pro_user_can_invite_more_than_three_members(): void
    {
        $this->createProSubscription($this->user);

        $this->attachActiveMembers(3);

        $invitee = User::factory()->create();

        $response = $this->inviteMember($invitee->email);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'project', 'invited_user']);
    }

    //  Meetings
    #[Test]
    public function free_user_is_blocked_from_creating_a_second_meeting(): void
    {
        Meeting::factory()->for($this->user)->for($this->project)->create();

        $this->mock(Zoom::class);

        $response = $this->createMeeting();

        $this->assertPlanLimitExceeded($response, 'limit_reached', 'meetings', 1, 1);
    }

    #[Test]
    public function pro_user_can_create_more_than_one_meeting(): void
    {
        $this->createProSubscription($this->user);

        Meeting::factory()->for($this->user)->for($this->project)->create();

        $this->mock(Zoom::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createMeeting')->once()->andReturn(
                new ZoomMeetingDTO(
                    meeting_id: 9999999999,
                    topic: 'Pro Meeting',
                    agenda: 'Agenda',
                    created_at: now()->toDateTimeString(),
                    duration: 30,
                    start_time: now()->addDay()->toDateTimeString(),
                    start_url: 'https://zoom.us/s/start',
                    join_url: 'https://zoom.us/j/join',
                    status: 'waiting',
                    timezone: 'UTC',
                    password: 'abc1234',
                    join_before_host: false,
                )
            );
        });

        $response = $this->createMeeting();

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Meeting Created Successfully');
    }

    //  API Tokens
    #[Test]
    public function free_user_is_blocked_from_creating_a_second_api_token(): void
    {
        $this->seedExistingApiToken();

        $response = $this->createApiToken('Blocked Token');

        $this->assertPlanLimitExceeded($response, 'limit_reached', 'api_tokens', 1, 1);
    }

    #[Test]
    public function pro_user_can_create_multiple_api_tokens(): void
    {
        $this->createProSubscription($this->user);

        $this->seedExistingApiToken();

        $response = $this->createApiToken('Second Token');

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Token created successfully.');
    }

    #[Test]
    public function plan_limit_exceeded_response_contains_all_required_fields(): void
    {
        Project::factory()->count(2)->for($this->user)->create();

        $response = $this->createProject([
            'name' => 'Blocked',
            'about' => 'Testing response',
            'stage_id' => 1,
        ]);

        $response->assertStatus(403)
            ->assertJsonStructure([
                'message',
                'error_type',
                'reason',
                'limit_type',
                'current_usage',
                'max_allowed',
                'upgrade_required',
            ]);
    }

    //  Grace Period — Pro access retained
    #[Test]
    public function grace_period_user_can_create_beyond_free_project_limit(): void
    {
        $this->createGracePeriodSubscription($this->user);

        Project::factory()->count(3)->for($this->user)->create();

        $response = $this->createProject([
            'name' => 'Fourth Project During Grace',
            'about' => 'Grace user project',
            'stage_id' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Project Created Successfully');
    }

    #[Test]
    public function grace_period_user_can_create_beyond_free_task_limit(): void
    {
        $this->createGracePeriodSubscription($this->user);

        $this->createActiveTasks(10);

        $response = $this->createTask(['title' => 'Eleventh Task During Grace']);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Task added Successfully');
    }

    //  Post-Grace — enforced as Free
    #[Test]
    public function post_grace_user_is_blocked_from_creating_beyond_free_project_limit(): void
    {
        $this->createExpiredSubscription($this->user);

        Project::factory()->count(2)->for($this->user)->create();

        $response = $this->createProject([
            'name' => 'Blocked Post-Grace Project',
            'about' => 'Should not be created',
            'stage_id' => 1,
        ]);

        $this->assertPlanLimitExceeded($response, 'limit_reached', 'projects', 3, 3);
    }

    #[Test]
    public function post_grace_user_is_blocked_from_creating_beyond_free_task_limit(): void
    {
        $this->createExpiredSubscription($this->user);

        $this->createActiveTasks(10);

        $response = $this->createTask(['title' => 'Blocked Post-Grace Task']);

        $this->assertPlanLimitExceeded($response, 'limit_reached', 'active_tasks_per_project', 10, 10);
    }

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
     */
    private function createProject(array $payload): TestResponse
    {
        return $this->postJson(route('projects.store'), $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createTask(array $payload): TestResponse
    {
        return $this->postJson(route('tasks.store', $this->project), $payload);
    }

    private function inviteMember(string $email): TestResponse
    {
        return $this->postJson(route('send.invitation', $this->project), ['email' => $email]);
    }

    private function createMeeting(): TestResponse
    {
        return $this->postJson(route('meetings.store', $this->project), $this->validMeetingPayload());
    }

    private function createApiToken(string $name): TestResponse
    {
        return $this->postJson(route('api-tokens.store'), ['name' => $name]);
    }

    private function seedExistingApiToken(): void
    {
        $this->user->tokens()->create([
            'name' => 'existing-token',
            'token' => hash('sha256', Str::uuid()->toString()),
            'abilities' => ['*'],
            'last_used_at' => null,
            'expires_at' => null,
        ]);
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
}
