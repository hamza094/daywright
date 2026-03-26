<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Subscriptions;

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
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\FixtureHelpers;
use Tests\Traits\SubscriptionHelpers;

class PlanLimitServiceFeatureTest extends TestCase
{
    use FixtureHelpers, RefreshDatabase, SubscriptionHelpers;

    private User $user;

    private Project $project;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.paddle.monthly', 101);
        config()->set('services.paddle.yearly', 202);

        $this->createTaskStatuses();

        /** @var User $user */
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        /** @var Project $project */
        $project = Project::factory()->for($user)->create();

        $this->user = $user;
        $this->project = $project;

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

    //  Tasks
    #[Test]
    public function free_user_is_blocked_from_creating_an_eleventh_active_task(): void
    {
        $this->createActiveTasks(10);

        $response = $this->createTask(['title' => 'Blocked Task']);

        $this->assertPlanLimitExceeded($response, 'limit_reached', 'active_tasks_per_project', 10, 10);
    }

    //  Members / Invitations
    #[Test]
    public function free_user_is_blocked_from_inviting_a_fourth_member(): void
    {
        $this->attachActiveMembers(3);

        /** @var User $invitee */
        $invitee = User::factory()->create();

        $response = $this->inviteMember($invitee->email);

        $this->assertPlanLimitExceeded($response, 'limit_reached', 'members', 3, 3);
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

    //  API Tokens
    #[Test]
    public function free_user_is_blocked_from_creating_a_second_api_token(): void
    {
        $this->seedExistingApiToken();

        $response = $this->createApiToken('Blocked Token');

        $this->assertPlanLimitExceeded($response, 'limit_reached', 'api_tokens', 1, 1);
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
