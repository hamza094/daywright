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

        $response = $this->postJson(route('projects.store'), [
            'name' => 'Blocked Project',
            'about' => 'Should not be created',
            'stage_id' => 1,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error_type' => 'plan_limit_exceeded',
                'reason' => 'limit_reached',
                'limit_type' => 'projects',
                'current_usage' => 3,
                'max_allowed' => 3,
                'upgrade_required' => true,
            ]);

        $this->assertDatabaseMissing('projects', ['name' => 'Blocked Project']);
    }

    #[Test]
    public function pro_user_can_create_more_than_three_projects(): void
    {
        $this->createProSubscription($this->user);

        Project::factory()->count(3)->for($this->user)->create();

        $response = $this->postJson(route('projects.store'), [
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
        Task::factory()->count(10)->for($this->user, 'owner')->for($this->project)->create([
            'status_id' => TaskStatusEnum::PENDING,
        ]);

        $response = $this->postJson(
            route('tasks.store', $this->project),
            ['title' => 'Blocked Task']
        );

        $response->assertStatus(403)
            ->assertJson([
                'error_type' => 'plan_limit_exceeded',
                'reason' => 'limit_reached',
                'limit_type' => 'active_tasks_per_project',
                'current_usage' => 10,
                'max_allowed' => 10,
                'upgrade_required' => true,
            ]);
    }

    #[Test]
    public function pro_user_can_create_more_than_ten_active_tasks(): void
    {
        $this->createProSubscription($this->user);

        Task::factory()->count(10)->for($this->user, 'owner')->for($this->project)->create([
            'status_id' => TaskStatusEnum::PENDING,
        ]);

        $response = $this->postJson(
            route('tasks.store', $this->project),
            ['title' => 'Eleventh Task']
        );

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Task added Successfully');
    }

    //  Members / Invitations
    #[Test]
    public function free_user_is_blocked_from_inviting_a_fourth_member(): void
    {
        $this->project->members()->attach(
            User::factory()->count(3)->create(),
            ['active' => true]
        );

        $invitee = User::factory()->create();

        $response = $this->postJson(
            route('send.invitation', $this->project),
            ['email' => $invitee->email]
        );

        $response->assertStatus(403)
            ->assertJson([
                'error_type' => 'plan_limit_exceeded',
                'reason' => 'limit_reached',
                'limit_type' => 'members',
                'current_usage' => 3,
                'max_allowed' => 3,
                'upgrade_required' => true,
            ]);
    }

    #[Test]
    public function pro_user_can_invite_more_than_three_members(): void
    {
        $this->createProSubscription($this->user);

        $this->project->members()->attach(
            User::factory()->count(3)->create(),
            ['active' => true]
        );

        $invitee = User::factory()->create();

        $response = $this->postJson(
            route('send.invitation', $this->project),
            ['email' => $invitee->email]
        );

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'project', 'invited_user']);
    }

    //  Meetings
    #[Test]
    public function free_user_is_blocked_from_creating_a_second_meeting(): void
    {
        Meeting::factory()->for($this->user)->for($this->project)->create();

        $this->mock(Zoom::class);

        $response = $this->postJson(
            route('meetings.store', $this->project),
            $this->validMeetingPayload()
        );

        $response->assertStatus(403)
            ->assertJson([
                'error_type' => 'plan_limit_exceeded',
                'reason' => 'limit_reached',
                'limit_type' => 'meetings',
                'current_usage' => 1,
                'max_allowed' => 1,
                'upgrade_required' => true,
            ]);
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

        $response = $this->postJson(
            route('meetings.store', $this->project),
            $this->validMeetingPayload()
        );

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Meeting Created Successfully');
    }

    //  API Tokens
    #[Test]
    public function free_user_is_blocked_from_creating_a_second_api_token(): void
    {
        $this->user->createToken('existing-token');

        $response = $this->postJson(route('api-tokens.store'), [
            'name' => 'Blocked Token',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error_type' => 'plan_limit_exceeded',
                'reason' => 'limit_reached',
                'limit_type' => 'api_tokens',
                'current_usage' => 1,
                'max_allowed' => 1,
                'upgrade_required' => true,
            ]);
    }

    #[Test]
    public function pro_user_can_create_multiple_api_tokens(): void
    {
        $this->createProSubscription($this->user);

        $this->user->createToken('existing-token');

        $response = $this->postJson(route('api-tokens.store'), [
            'name' => 'Second Token',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Token created successfully.');
    }

    #[Test]
    public function plan_limit_exceeded_response_contains_all_required_fields(): void
    {
        Project::factory()->count(2)->for($this->user)->create();

        $response = $this->postJson(route('projects.store'), [
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
